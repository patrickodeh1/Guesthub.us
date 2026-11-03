/**
 * Photo Uploader Component
 *
 * Background upload queue for room photos:
 *  - every photo is added to the queue the moment it is captured
 *  - each photo is downscaled in the browser, then uploaded one at a time
 *  - failed photos stay on screen with status 'failed' and can be retried
 *  - nothing here triggers a checklist re-render (that would destroy this
 *    component and any photo still on its way back from the camera)
 *
 * Public names (previews, uploading, uploadProgress, hover, handleFiles,
 * handleDrop, removePreview, formatFileSize, handleSubmit) are unchanged so
 * the existing template keeps working. handleSubmit now means "retry failed".
 */

const MAX_EDGE = 1920;        // longest side in px after downscale
const JPEG_QUALITY = 0.8;
const SKIP_BELOW_BYTES = 400 * 1024; // already small, send as-is
const MAX_ATTEMPTS = 3;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function loadImage(url) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('Image decode failed'));
        img.src = url;
    });
}

/**
 * Downscale + re-encode as JPEG. Falls back to the original file on any error.
 * Uses <img> + canvas so EXIF orientation is applied by the browser.
 */
async function compressImage(file) {
    if (file.size < SKIP_BELOW_BYTES) {
        return file;
    }

    const sourceUrl = URL.createObjectURL(file);
    try {
        const img = await loadImage(sourceUrl);
        const longest = Math.max(img.naturalWidth, img.naturalHeight);
        const scale = Math.min(1, MAX_EDGE / longest);
        const width = Math.round(img.naturalWidth * scale);
        const height = Math.round(img.naturalHeight * scale);

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d').drawImage(img, 0, 0, width, height);

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', JPEG_QUALITY));

        // Release canvas memory (matters on iOS Safari)
        canvas.width = 0;
        canvas.height = 0;

        if (!blob || blob.size >= file.size) {
            return file;
        }

        const baseName = (file.name || 'photo').replace(/\.[^.]+$/, '');
        return new File([blob], baseName + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
    } catch (error) {
        console.warn('Compression failed, uploading original:', error);
        return file;
    } finally {
        URL.revokeObjectURL(sourceUrl);
    }
}

export default function photoUploader(roomId) {
    return {
        roomId: roomId,
        previews: [],
        hover: false,
        uploading: false,
        uploadProgress: 0,

        uploadUrl: null,
        _running: false,
        _nextId: 1,
        _runTotal: 0,
        _runDone: 0,

        init() {
            // $el is the x-data root; the form (and its action) lives inside it
            this.uploadUrl = this.$el.querySelector('form')?.action || null;
        },

        // Publish unsent count so navigation buttons can warn before leaving the room
        syncPending() {
            window.__photoPending = window.__photoPending || {};
            window.__photoPending[this.roomId] = this.previews.filter((p) => p.status !== 'done').length;
        },

        destroy() {
            this.previews.forEach((p) => {
                if (p.url && p.url.startsWith('blob:')) URL.revokeObjectURL(p.url);
            });
            if (window.__photoPending) delete window.__photoPending[this.roomId];
        },

        get failedCount() {
            return this.previews.filter((p) => p.status === 'failed').length;
        },

        get pendingCount() {
            return this.previews.filter((p) => p.status !== 'failed' && p.status !== 'done').length;
        },

        handleDrop(event) {
            event.preventDefault();
            this.hover = false;
            this.addFiles(Array.from(event.dataTransfer.files));
        },

        handleFiles(event) {
            const files = Array.from(event.target.files || []);
            this.addFiles(files);
            // Reset so the same camera/gallery action can fire again
            event.target.value = '';
        },

        addFiles(files) {
            let added = 0;
            files.forEach((file) => {
                if (!file.type.startsWith('image/')) {
                    return;
                }
                // Add immediately (no waiting on image decode) so a photo can
                // never silently disappear before it is queued.
                this.previews.push({
                    id: this._nextId++,
                    file: file,
                    url: '',
                    size: file.size,
                    name: file.name,
                    status: 'queued',
                    error: null,
                });
                added++;
            });

            if (added > 0) {
                this._runTotal += added;
                this.syncPending();
                this.processQueue();
            }
        },

        removePreview(index) {
            const preview = this.previews[index];
            if (!preview) return;
            // Can't cancel something already being processed or sent
            if (preview.status === 'processing' || preview.status === 'uploading') {
                return;
            }
            if (preview.status === 'done') {
                this.deleteDone(preview);
                return;
            }
            if (preview.url && preview.url.startsWith('blob:')) {
                URL.revokeObjectURL(preview.url);
            }
            this.previews.splice(index, 1);
            this.syncPending();
        },

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },

        // Form submit / Upload button now means "retry failed photos"
        async handleSubmit(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            const failed = this.previews.filter((p) => p.status === 'failed');
            if (failed.length === 0) return;

            failed.forEach((p) => {
                p.status = 'queued';
                p.error = null;
            });
            this._runTotal += failed.length;
            this.processQueue();
        },

        async processQueue() {
            if (this._running) return; // the running loop will pick up new items
            this._running = true;
            this.uploading = true;

            let uploaded = 0;
            let failed = 0;

            try {
                let next;
                while ((next = this.previews.find((p) => p.status === 'queued'))) {
                    const ok = await this.processOne(next.id);
                    this._runDone++;
                    this.uploadProgress = Math.round(this._runDone / Math.max(this._runTotal, 1) * 100);
                    if (ok) uploaded++; else failed++;
                }
            } finally {
                this._running = false;
                this.uploading = false;
                this.uploadProgress = 0;
                this._runTotal = 0;
                this._runDone = 0;
            }

            const handler = window.checklistHandler;
            if (handler) {
                if (failed > 0) {
                    handler.showError(`${failed} photo(s) failed to upload. Tap Upload to retry.`);
                } else if (uploaded > 0) {
                    handler.showSuccess(`${uploaded} photo(s) uploaded.`);
                }
            }
        },

        async processOne(id) {
            const entry = this.previews.find((p) => p.id === id);
            if (!entry) return false;

            try {
                entry.status = 'processing';
                const file = await compressImage(entry.file);
                entry.file = file;
                entry.size = file.size;
                entry.url = URL.createObjectURL(file);

                entry.status = 'uploading';

                const url = this.uploadUrl || this.$el.querySelector('form')?.action;
                let lastError = null;

                for (let attempt = 1; attempt <= MAX_ATTEMPTS; attempt++) {
                    try {
                        const formData = new FormData();
                        formData.append('photos[]', file);
                        const response = await window.api.post(url, formData);
                        if (response && (response.success || response.photos)) {
                            lastError = null;
                            entry.serverPhoto = (response.photos && response.photos[0]) || response.photo || null;
                            break;
                        }
                        lastError = new Error('Upload returned a non-success response');
                    } catch (err) {
                        lastError = err;
                    }
                    if (attempt < MAX_ATTEMPTS) {
                        await sleep(1000 * attempt);
                    }
                }

                if (lastError) {
                    throw lastError;
                }

                entry.file = null;
                entry.status = 'done';
                this.syncPending();
                this.bumpCount(1);
                return true;
            } catch (error) {
                console.error('Photo upload failed:', error);
                entry.status = 'failed';
                entry.error = error?.response?.data?.message || error?.message || 'Upload failed';
                return false;
            }
        },

        async deleteDone(preview) {
            const photoId = preview.serverPhoto && preview.serverPhoto.id;
            if (!photoId) return;
            if (!confirm('Delete this uploaded photo?')) return;
            const sessionId = document.querySelector('[data-session-id]')?.dataset.sessionId
                || window.location.pathname.match(/\/sessions\/([^\/]+)/)?.[1];
            if (!sessionId) return;
            try {
                const res = await fetch(`/sessions/${sessionId}/photos/${photoId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                this.removeById(preview.id);
                this.bumpCount(-1);
            } catch (err) {
                console.error('Delete failed:', err);
                alert('Could not delete the photo. Please try again.');
            }
        },

        removeById(id) {
            const index = this.previews.findIndex((p) => p.id === id);
            if (index === -1) return;
            const url = this.previews[index].url;
            if (url && url.startsWith('blob:')) {
                URL.revokeObjectURL(url);
            }
            this.previews.splice(index, 1);
            this.syncPending();
        },

        // Update the visible "x / y Photos Req." counter and the cached count
        // locally. Deliberately does NOT call renderer.refresh() or
        // renderChecklist(): a re-render destroys this component mid-capture.
        // Once the room minimum is met, enable Next/Finish and clear the warning
        // without re-rendering the checklist.
        updateNav() {
            const countEl = this.$el.closest('[data-room-photos]')?.querySelector('[data-photo-count]');
            const m = countEl?.textContent.match(/(\d+)\s*\/\s*(\d+)/);
            if (!m) return;
            if (parseInt(m[1], 10) < parseInt(m[2], 10)) return;

            countEl.classList.remove('text-amber-500');
            countEl.classList.add('text-gray-500');
            document.querySelectorAll('[data-photo-next]').forEach((btn) => {
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
            });
            document.querySelectorAll('[data-photo-warning]').forEach((el) => el.remove());
        },

        bumpCount(n) {
            setTimeout(() => this.updateNav(), 0);
            const roomContainer = this.$el.closest('[data-room-photos]');
            const countEl = roomContainer?.querySelector('[data-photo-count]');
            if (countEl) {
                const match = countEl.textContent.match(/(\d+)/);
                if (match) {
                    countEl.textContent = countEl.textContent.replace(/\d+/, parseInt(match[1], 10) + n);
                }
            }

            const renderer = document.querySelector('[x-data*="checklistRenderer"]')?._x_dataStack?.[0];
            const counts = renderer?.sessionData?.photo_counts;
            if (counts) {
                counts[this.roomId] = (counts[this.roomId] || 0) + n;
            }
        },
    };
}
