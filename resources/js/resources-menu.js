/**
 * Resources Menu Alpine.js Component
 */
export default function resourcesMenu() {
    return {
        isOpen: false,
        loading: false,
        error: null,
        videos: [],
        activeCategory: '',
        activeVideo: null,

        init() {
            // Register ESC listener for video player
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.activeVideo) {
                    this.closePlayer();
                }
            });
        },

        async open(propertyId) {
            this.isOpen = true;
            this.error = null;
            await this.fetchVideos(propertyId);
        },

        close() {
            this.isOpen = false;
        },

        async fetchVideos(propertyId) {
            this.loading = true;
            this.error = null;
            try {
                const response = await fetch(`/api/property-videos?property_id=${propertyId}`);
                if (!response.ok) {
                    throw new Error('Failed to fetch videos');
                }
                this.videos = await response.json();
            } catch (err) {
                console.error('Error fetching videos:', err);
                this.error = err.message || 'Could not load videos.';
            } finally {
                this.loading = false;
            }
        },

        get filteredVideos() {
            if (!this.activeCategory) {
                return this.videos;
            }
            return this.videos.filter(v => v.category === this.activeCategory);
        },

        playVideo(video) {
            this.activeVideo = video;
            document.body.classList.add('overflow-hidden');
            
            // Wait for DOM update to find and play the video element
            this.$nextTick(() => {
                const videoEl = this.$refs.videoPlayer;
                if (videoEl) {
                    videoEl.focus();
                    videoEl.play().catch(e => console.log('Autoplay prevented:', e));
                }
            });
        },

        closePlayer() {
            const videoEl = this.$refs.videoPlayer;
            if (videoEl) {
                videoEl.pause();
            }
            this.activeVideo = null;
            document.body.classList.remove('overflow-hidden');
        },

        togglePlay() {
            const videoEl = this.$refs.videoPlayer;
            if (videoEl) {
                if (videoEl.paused) {
                    videoEl.play();
                } else {
                    videoEl.pause();
                }
            }
        }
    };
}
