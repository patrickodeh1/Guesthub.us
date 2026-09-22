<x-admin-layout :title="$step->exists ? 'Edit Step' : 'Add Step'">
    <div class="page-header">
        <div>
            <p class="eyebrow">{{ ucfirst($type) }} instructions</p>
            <h1 class="page-title">{{ $step->exists ? 'Edit Step' : 'Add Step' }}</h1>
        </div>
        <a href="{{ route('admin.instructions.show', $selectedProperty->id) }}" class="btn-secondary">Back</a>
    </div>

    <form method="post" enctype="multipart/form-data"
          action="{{ $step->exists ? route('admin.instructions.update', $step) : route('admin.instructions.store') }}"
          class="max-w-3xl mx-auto card card-pad grid gap-5">
        @csrf
        @if($step->exists) @method('put') @endif
        <input type="hidden" name="property_id" value="{{ $selectedProperty->id }}">
        <input type="hidden" name="type" value="{{ $type }}">

        <div class="grid gap-5 sm:grid-cols-2">
            <label class="field-label sm:col-span-2">
                Property
                <input value="{{ $selectedProperty->name }}" disabled class="input bg-slate-50">
            </label>

            <label class="field-label sm:col-span-2">
                Step Title <span class="text-red-600">*</span>
                <input name="title" id="step_title_input" value="{{ old('title', $step->title) }}" required placeholder="e.g. Find the key lockbox" class="input">
            </label>

            <label class="field-label sm:col-span-2">
                Step Type
                @php $currentAction = old('action', $step->action ?? 'content'); @endphp
                <select name="action" class="input mt-1">
                    <option value="content" @selected($currentAction === 'content')>Regular content step</option>
                    <option value="door_lock" @selected($currentAction === 'door_lock')>Door Lock/Unlock card</option>
                </select>
            </label>
        </div>

        <div class="field-label">
            <span class="mb-1 block">Content</span>
            <div class="mb-2 flex flex-wrap gap-2" id="token-buttons">
                <button type="button" data-token="[[guest_name]]" class="token-btn rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono font-semibold text-slate-600 hover:bg-slate-100">Guest Name</button>
                <button type="button" data-token="[[guest_first_name]]" class="token-btn rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono font-semibold text-slate-600 hover:bg-slate-100">First Name</button>
                <button type="button" data-token="[[booking_id]]" class="token-btn rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono font-semibold text-slate-600 hover:bg-slate-100">Booking ID</button>
                <button type="button" data-token="[[check_in_date]]" class="token-btn rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono font-semibold text-slate-600 hover:bg-slate-100">Check-in Date</button>
                <button type="button" data-token="[[check_out_date]]" class="token-btn rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono font-semibold text-slate-600 hover:bg-slate-100">Check-out Date</button>
                <button type="button" data-token="[[property_name]]" class="token-btn rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono font-semibold text-slate-600 hover:bg-slate-100">Property Name</button>
                <button type="button" data-token="[[property_address]]" class="token-btn rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono font-semibold text-slate-600 hover:bg-slate-100">Address</button>
                <button type="button" data-token="[[lockbox_code]]" class="token-btn rounded border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-mono font-semibold text-slate-600 hover:bg-slate-100">Lockbox Code</button>
            </div>
            <textarea id="content-editor" name="content">{{ old('content', $step->content) }}</textarea>
            <p class="mt-1 text-xs text-slate-400">Use the token buttons above to insert guest details like name, booking ID, dates, etc.</p>
        </div>

        <label class="field-label">
            Step Image
            @if($step->imageUrl())
                <img id="step_image_preview" src="{{ $step->imageUrl() }}" class="mt-2 h-40 w-full rounded-xl object-cover">
            @else
                <img id="step_image_preview" src="" class="mt-2 h-40 w-full rounded-xl object-cover hidden">
            @endif
            <div class="mt-2 flex gap-2">
                <span class="file-box flex-1">
                    <x-icon name="upload" class="mb-2 h-7 w-7 text-[#b08a45]" />
                    Upload a photo for this step
                    <input type="file" name="image_path" id="step_image_input" accept="image/*" class="sr-only">
                </span>
            </div>
            <button type="button" onclick="openMediaPicker()" class="btn-secondary mt-2 w-full text-sm">Choose from Library</button>
            <input type="hidden" name="existing_image_path" id="existing_image_path" value="{{ $step->image_path }}">
            <p class="mt-1 text-xs text-slate-400">Shows above the step content so guests have a visual reference.</p>
        </label>

        <label class="field-label">
            Additional Photos
            <div id="gallery-existing" class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                @foreach($step->images ?? [] as $img)
                    <div class="relative group" data-image-id="{{ $img->id }}">
                        <img src="{{ $img->imageUrl() }}" class="h-24 w-full rounded-lg object-cover">
                        <button type="button" class="gallery-delete-btn absolute right-1 top-1 rounded-full bg-red-600/90 p-1 text-white opacity-0 transition group-hover:opacity-100" data-image-id="{{ $img->id }}">
                            <x-icon name="x" class="h-3 w-3" />
                        </button>
                    </div>
                @endforeach
            </div>
            <div id="gallery-new-previews" class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4"></div>
            <div id="gallery-library-inputs"></div>
            <div class="mt-2 flex gap-2">
                <span class="file-box flex-1">
                    <x-icon name="upload" class="mb-2 h-7 w-7 text-[#b08a45]" />
                    Select one or more photos
                    <input type="file" name="images[]" id="step_images_input" accept="image/*" multiple class="sr-only">
                </span>
            </div>
            <button type="button" onclick="openMediaPicker('gallery')" class="btn-secondary mt-2 w-full text-sm">Choose from Library</button>
            <p class="mt-1 text-xs text-slate-400">Pick as many as you need. Tap the &times; on any thumbnail to drop it before saving.</p>
        </label>

        <style>
        .tox-tinymce { z-index: 1 !important; }
        .tox .tox-dialog-wrap { z-index: 99998 !important; }
        </style>

        {{-- Media Picker Modal --}}
        <div id="media-picker-modal" class="fixed inset-0 z-[99999] hidden items-center justify-center bg-slate-950/40 p-4">
            <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p id="media-picker-breadcrumb" class="text-sm font-bold text-slate-700">Library</p>
                    <div class="flex items-center gap-2">
                        <label class="btn-secondary cursor-pointer text-xs">
                            Upload
                            <input type="file" id="media-picker-upload-input" accept="image/*" class="sr-only">
                        </label>
                        <button type="button" onclick="closeMediaPicker()" class="text-slate-400 hover:text-slate-700">
                            <x-icon name="x" class="h-5 w-5" />
                        </button>
                    </div>
                </div>
                <div id="media-picker-body" class="grid max-h-96 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4"></div>
            </div>
        </div>

        <label class="flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm font-semibold">
            <span>Active</span>
            <input type="checkbox" name="active" value="1" @checked(old('active', $step->active ?? true)) class="rounded border-slate-300">
        </label>

        <label class="field-label mt-3">
            Who should see this step?
            <select name="visibility" class="input mt-1">
                @php $currentVisibility = old('visibility', $step->visibility ?? 'all'); @endphp
                <option value="all" @selected($currentVisibility === 'all')>Show to all guests</option>
                <option value="parkers_only" @selected($currentVisibility === 'parkers_only')>Show to parking guests only</option>
                <option value="non_parkers_only" @selected($currentVisibility === 'non_parkers_only')>Show to non-parking guests only</option>
            </select>
        </label>

        <button class="btn-primary">{{ $step->exists ? 'Save Changes' : 'Add Step' }}</button>
    </form>

    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Merriweather:wght@400;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
<style>
.tox-tinymce-aux, .tox.tox-silver-sink, .tox-dialog-wrap { z-index: 1000 !important; }
.tox-menu.tox-collection.tox-collection--list { max-height: 320px !important; overflow-y: auto !important; }
.tox.tox-tinymce.tox-fullscreen,
body.tox-fullscreen-body .tox.tox-tinymce.tox-fullscreen {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    z-index: 2147483001 !important;
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js"></script>
    <script>
    document.getElementById('step_image_input').addEventListener('change', function (e) {
        const file = e.target.files[0];
        const preview = document.getElementById('step_image_preview');
        if (file) {
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
            document.getElementById('existing_image_path').value = '';
        }
    });

    function capitalizeSentences(str) {
        return str.replace(/(^\s*[a-z])|([.!?]\s+[a-z])/g, function (c) {
            return c.toUpperCase();
        });
    }

    document.getElementById('step_title_input').addEventListener('input', function () {
        const cursorPos = this.selectionStart;
        this.value = capitalizeSentences(this.value);
        this.setSelectionRange(cursorPos, cursorPos);
    });

    let pendingGalleryFiles = [];
    let pendingLibraryImages = [];

    function syncPendingGalleryInput() {
        const dt = new DataTransfer();
        pendingGalleryFiles.forEach(function (file) { dt.items.add(file); });
        document.getElementById('step_images_input').files = dt.files;
    }

    function syncLibraryInputs() {
        const container = document.getElementById('gallery-library-inputs');
        container.innerHTML = '';
        pendingLibraryImages.forEach(function (item) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'gallery_library_paths[]';
            input.value = item.path;
            container.appendChild(input);
        });
    }

    function renderPendingGalleryPreviews() {
        const previews = document.getElementById('gallery-new-previews');
        previews.innerHTML = '';

        pendingGalleryFiles.forEach(function (file, idx) {
            const wrap = document.createElement('div');
            wrap.className = 'relative group';
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.className = 'h-24 w-full rounded-lg object-cover';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'absolute right-1 top-1 rounded-full bg-red-600/90 px-1.5 py-0.5 text-xs font-bold text-white opacity-0 transition group-hover:opacity-100';
            btn.innerHTML = '&times;';
            btn.addEventListener('click', function () {
                pendingGalleryFiles.splice(idx, 1);
                syncPendingGalleryInput();
                renderPendingGalleryPreviews();
            });
            wrap.appendChild(img);
            wrap.appendChild(btn);
            previews.appendChild(wrap);
        });

        pendingLibraryImages.forEach(function (item, idx) {
            const wrap = document.createElement('div');
            wrap.className = 'relative group';
            const img = document.createElement('img');
            img.src = item.url;
            img.className = 'h-24 w-full rounded-lg object-cover';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'absolute right-1 top-1 rounded-full bg-red-600/90 px-1.5 py-0.5 text-xs font-bold text-white opacity-0 transition group-hover:opacity-100';
            btn.innerHTML = '&times;';
            btn.addEventListener('click', function () {
                pendingLibraryImages.splice(idx, 1);
                syncLibraryInputs();
                renderPendingGalleryPreviews();
            });
            wrap.appendChild(img);
            wrap.appendChild(btn);
            previews.appendChild(wrap);
        });
    }

    document.getElementById('step_images_input').addEventListener('change', function (e) {
        pendingGalleryFiles = pendingGalleryFiles.concat([...e.target.files]);
        syncPendingGalleryInput();
        renderPendingGalleryPreviews();
    });

    document.getElementById('gallery-existing').addEventListener('click', function (e) {
        const btn = e.target.closest('.gallery-delete-btn');
        if (!btn) return;
        if (!confirm('Remove this photo?')) return;
        const id = btn.dataset.imageId;
        fetch('{{ url("admin/instructions/images") }}/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        }).then(r => r.json()).then(function (data) {
            if (data.ok) {
                btn.closest('[data-image-id]').remove();
            }
        });
    });

    let mediaPickerFolderId = null;
    let mediaPickerMode = 'thumbnail';

    function openMediaPicker(mode) {
        mediaPickerMode = mode || 'thumbnail';
        document.getElementById('media-picker-modal').classList.remove('hidden');
        document.getElementById('media-picker-modal').classList.add('flex');
        loadMediaPicker(null);
    }

    function closeMediaPicker() {
        document.getElementById('media-picker-modal').classList.add('hidden');
        document.getElementById('media-picker-modal').classList.remove('flex');
    }

    function loadMediaPicker(folderId) {
        mediaPickerFolderId = folderId;
        const url = '{{ route("admin.media.picker") }}' + (folderId ? '?folder_id=' + folderId : '');
        fetch(url).then(r => r.json()).then(data => {
            const body = document.getElementById('media-picker-body');
            const crumbText = data.breadcrumb.length ? data.breadcrumb.map(c => c.name).join(' / ') : 'Library';
            document.getElementById('media-picker-breadcrumb').textContent = crumbText;
            body.innerHTML = '';

            if (folderId !== null) {
                const up = document.createElement('button');
                up.type = 'button';
                up.className = 'col-span-full text-left text-xs font-semibold text-blue-600 hover:underline';
                up.textContent = '← Back';
                const parentId = data.breadcrumb.length > 1 ? data.breadcrumb[data.breadcrumb.length - 2].id : null;
                up.onclick = () => loadMediaPicker(parentId);
                body.appendChild(up);
            }

            data.folders.forEach(folder => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'flex flex-col items-center gap-1 rounded-lg border border-slate-200 p-3 hover:bg-slate-50';
                el.innerHTML = '<span class="text-2xl">📁</span><span class="truncate text-xs font-semibold">' + folder.name + '</span>';
                el.onclick = () => loadMediaPicker(folder.id);
                body.appendChild(el);
            });

            data.files.forEach(file => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'overflow-hidden rounded-lg border border-slate-200 bg-slate-50 hover:ring-2 hover:ring-blue-400';
                el.innerHTML = '<img src="' + file.url + '" class="h-20 w-full object-contain p-1">';
                el.onclick = () => selectMediaFile(file);
                body.appendChild(el);
            });

            if (!data.folders.length && !data.files.length) {
                body.innerHTML += '<p class="col-span-full text-center text-sm text-slate-400">No images in this folder yet.</p>';
            }
        });
    }

    function selectMediaFile(file) {
        if (mediaPickerMode === 'editor') {
            if (window.__contentEditorInstance) {
                window.__contentEditorInstance.insertContent('<img src="' + file.url + '" alt="' + (file.name || '') + '" style="max-width:100%;">');
            }
            closeMediaPicker();
            return;
        }
        if (mediaPickerMode === 'gallery') {
            pendingLibraryImages.push({ path: file.path, url: file.url });
            syncLibraryInputs();
            renderPendingGalleryPreviews();
            return;
        }
        document.getElementById('step_image_preview').src = file.url;
        document.getElementById('step_image_preview').classList.remove('hidden');
        document.getElementById('existing_image_path').value = file.path;
        document.getElementById('step_image_input').value = '';
        closeMediaPicker();
    }

    document.getElementById('media-picker-upload-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('image', file);
        if (mediaPickerFolderId) formData.append('media_folder_id', mediaPickerFolderId);
        fetch('{{ route("admin.media.files.store") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData,
        }).then(() => {
            loadMediaPicker(mediaPickerFolderId);
            e.target.value = '';
        });
    });
    tinymce.init({
        relative_urls: false,
        remove_script_host: false,
        selector: '#content-editor',
        plugins: 'lists advlist link code table searchreplace wordcount visualblocks charmap emoticons preview anchor fullscreen nonbreaking',
        toolbar: 'undo redo | bold italic underline forecolor backcolor | alignleft aligncenter alignright | bullist numlist | customlineheight | link insertimage table anchor charmap emoticons | searchreplace preview fullscreen | removeformat code | fontfamily fontsize',
        browser_spellcheck: true,
        contextmenu: false,
        valid_styles: {
            '*': 'font-size,font-family,color,background-color,text-align,text-decoration,line-height'
        },
        menubar: false,
        toolbar_mode: 'wrap',
        height: 320,
        ui_mode: 'split',
        font_size_formats: '8px 10px 12px 14px 16px 18px 20px 24px 28px 32px 36px 42px 48px 60px 72px',
        font_family_formats:
            'Arial=arial,helvetica,sans-serif;' +
            'Helvetica=helvetica,arial,sans-serif;' +
            'Times New Roman=times new roman,times,serif;' +
            'Georgia=georgia,palatino,serif;' +
            'Garamond=garamond,serif;' +
            'Verdana=verdana,geneva,sans-serif;' +
            'Tahoma=tahoma,arial,helvetica,sans-serif;' +
            'Trebuchet MS=trebuchet ms,geneva,sans-serif;' +
            'Courier New=courier new,courier,monospace;' +
            'Comic Sans MS=comic sans ms,sans-serif;' +
            'Impact=impact,sans-serif;' +
            'Lucida Sans=lucida sans unicode,lucida grande,sans-serif;' +
            'Roboto=Roboto,arial,sans-serif;' +
            'Open Sans=\'Open Sans\',arial,sans-serif;' +
            'Montserrat=Montserrat,arial,sans-serif;' +
            'Merriweather=Merriweather,georgia,serif;' +
            'Playfair Display=\'Playfair Display\',georgia,serif',
        color_map: [
            '000000','Black', '424242','Dark Gray', '757575','Gray', 'BDBDBD','Light Gray', 'FFFFFF','White',
            'B71C1C','Dark Red', 'E53935','Red', 'F44336','Bright Red', 'FF7043','Orange Red', 'FB8C00','Orange',
            'FDD835','Yellow', 'C0CA33','Olive', '7CB342','Light Green', '43A047','Green', '00897B','Teal',
            '00ACC1','Cyan', '1E88E5','Blue', '3949AB','Indigo', '5E35B1','Purple', '8E24AA','Magenta', 'D81B60','Pink'
        ],
        content_css: false,
        content_style: `
            @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Merriweather:wght@400;700&family=Playfair+Display:wght@400;700&display=swap');
            body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; }
            p { margin: 0; }
        `,
        promotion: false,
        branding: false,
        setup: function(editor) {
            window.__contentEditorInstance = editor;

            function textBeforeNode(node, root) {
                var text = '';
                function walk(n) {
                    if (n === node) return true;
                    if (n.nodeType === 3) {
                        text += n.nodeValue;
                    } else if (n.nodeType === 1) {
                        var tag = n.nodeName;
                        if (tag === 'P' || tag === 'DIV' || tag === 'LI' || tag === 'BR') {
                            text = '';
                        }
                    }
                    for (var i = 0; i < n.childNodes.length; i++) {
                        if (walk(n.childNodes[i])) return true;
                    }
                    return false;
                }
                walk(root);
                return text;
            }

            editor.on('input', function() {
                var sel = editor.selection;
                var rng = sel.getRng();
                var node = rng.startContainer;
                if (node.nodeType !== 3) return;
                var offset = rng.startOffset;
                if (offset === 0) return;
                var text = node.nodeValue;
                var charIndex = offset - 1;
                var ch = text.charAt(charIndex);
                if (!/[a-z]/.test(ch)) return;

                var before = text.substring(0, charIndex);
                var isSentenceStart = /^\s*$/.test(before) || /[.!?]\s+$/.test(before);

                if (isSentenceStart && /^\s*$/.test(before)) {
                    var blockText = textBeforeNode(node, editor.getBody());
                    isSentenceStart = /^\s*$/.test(blockText) || /[.!?]\s*$/.test(blockText);
                }

                if (isSentenceStart) {
                    node.nodeValue = text.substring(0, charIndex) + ch.toUpperCase() + text.substring(offset);
                    rng.setStart(node, offset);
                    rng.setEnd(node, offset);
                    sel.setRng(rng);
                }
            });

            document.querySelectorAll('.token-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    editor.insertContent('<span style="background:#fef9c3;padding:0 3px;border-radius:3px;font-family:monospace;">' + btn.dataset.token + '</span>');
                });
            });

            var lineHeightValues = ['0.3', '0.5', '0.7', '0.9', '1', '1.15', '1.3', '1.5', '1.75', '2', '2.5', '3', '3.5', '4', '4.5', '5'];
            var lastSelectionRange = null;
            editor.on('NodeChange KeyUp MouseUp', function() {
                try { lastSelectionRange = editor.selection.getRng().cloneRange(); } catch (e) {}
            });
            editor.ui.registry.addMenuButton('customlineheight', {
                icon: 'line-height',
                tooltip: 'Line height',
                fetch: function(callback) {
                    var currentValue = null;
                    try {
                        var node0 = editor.selection.getNode();
                        var block0 = editor.dom.getParent(node0, editor.dom.isBlock) || node0;
                        if (block0 && block0.nodeName !== 'BODY') {
                            currentValue = editor.dom.getStyle(block0, 'line-height') || null;
                        }
                    } catch (e) {}
                    var items = lineHeightValues.map(function(v) {
                        return {
                            type: 'togglemenuitem',
                            text: v,
                            active: currentValue === v,
                            onAction: function() {
                                editor.focus();
                                if (lastSelectionRange) {
                                    try { editor.selection.setRng(lastSelectionRange); } catch (e) {}
                                }
                                var blocks = editor.selection.getSelectedBlocks();
                                if (!blocks || !blocks.length) {
                                    var node = editor.selection.getNode();
                                    var single = editor.dom.getParent(node, editor.dom.isBlock) || node;
                                    blocks = [single];
                                }
                                var applied = 0;
                                blocks.forEach(function(block) {
                                    if (block && block.nodeName !== 'BODY') {
                                        editor.dom.setStyle(block, 'line-height', v);
                                        applied++;
                                    }
                                });
                                if (applied > 0) {
                                    editor.nodeChanged();
                                }
                            }
                        };
                    });
                    callback(items);
                }
            });

            editor.ui.registry.addButton('insertimage', {
                icon: 'image',
                tooltip: 'Insert image from library',
                onAction: function() {
                    openMediaPicker('editor');
                }
            });
        }
    });
    </script>
</x-admin-layout>
