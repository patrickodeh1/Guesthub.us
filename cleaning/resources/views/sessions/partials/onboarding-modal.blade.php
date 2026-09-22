@if(!empty($requiresOnboarding) && $requiresOnboarding)
<x-modal name="onboarding-modal" maxWidth="4xl">
    <div class="flex flex-col bg-gray-50 dark:bg-gray-900" style="max-height: 80vh;" x-data="onboardingFlow()">
        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex justify-between items-center shrink-0">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Property Review Required</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Please review these resources before starting. Required for your first 3 sessions here.</p>
            </div>
            {{-- Tabs / Progress --}}
            <div class="hidden sm:flex items-center gap-2">
                <template x-for="(tab, index) in tabs" :key="tab.id">
                    <div class="flex items-center">
                        <button type="button" 
                            @click="if(tab.unlocked) activeTab = tab.id"
                            class="flex items-center justify-center w-8 h-8 rounded-full text-sm font-semibold transition-colors"
                            :class="{
                                'bg-indigo-600 text-white': activeTab === tab.id,
                                'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-400': tab.completed && activeTab !== tab.id,
                                'bg-gray-200 text-gray-500 dark:bg-gray-700 dark:text-gray-400': !tab.completed && activeTab !== tab.id && tab.unlocked,
                                'bg-gray-100 text-gray-400 dark:bg-gray-800 dark:text-gray-600 cursor-not-allowed': !tab.unlocked
                            }">
                            <span x-show="!tab.completed" x-text="index + 1"></span>
                            <svg x-show="tab.completed" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </button>
                        <div x-show="index < tabs.length - 1" class="w-4 h-0.5 mx-1" :class="tab.completed ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-700'"></div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Content Area --}}
        <div class="flex-1 overflow-y-auto p-6" x-ref="scrollContainer" @scroll="checkScroll()">
            
            {{-- Videos Tab --}}
            <div x-show="activeTab === 'videos'">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">1. Instructional Videos</h3>
                
                @if(count($onboardingVideos) > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @foreach($onboardingVideos as $index => $video)
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden min-w-0 flex flex-col">
                                <div class="bg-black relative flex items-center justify-center w-full" style="aspect-ratio: 16 / 9;">
                                    <video id="video-{{ $video->id }}" src="{{ $video->video_url }}#t=0.1" class="w-full h-full object-contain" preload="metadata" playsinline controls></video>
                                </div>
                                <div class="p-4 flex items-start justify-between gap-3">
                                    <div>
                                        <h4 class="font-bold text-gray-900 dark:text-white">{{ $video->title }}</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">{{ $video->description ?: 'No description' }}</p>
                                    </div>
                                    <div x-show="videoStatus['{{ $video->id }}']" class="shrink-0 text-green-500 bg-green-50 dark:bg-green-900 p-1.5 rounded-full">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                        <p class="text-gray-500 dark:text-gray-400">No videos assigned to this property.</p>
                    </div>
                @endif
            </div>

            {{-- Photos Tab --}}
            <div x-show="activeTab === 'photos'">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">2. Staging Photos</h3>
                
                @if(count($onboardingPhotos) > 0)
                    <div class="space-y-6">
                        @foreach($onboardingPhotos->groupBy('task_name') as $taskName => $photos)
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-3 border-b border-gray-200 dark:border-gray-700 pb-2">{{ $taskName }}</h4>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                                    @foreach($photos as $photo)
                                        <div class="rounded-lg overflow-hidden bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700" style="aspect-ratio: 1 / 1;">
                                            @php
                                                // Resolve URL using standard method
                                                $url = $photo->media_url;
                                                if (!str_starts_with($url, 'http')) {
                                                    $path = ltrim(str_replace('\\', '/', $url), '/');
                                                    if (str_starts_with($path, 'storage/')) $path = substr($path, 8);
                                                    $url = url('file/' . $path);
                                                }
                                            @endphp
                                            <img src="{{ $url }}" alt="{{ $photo->media_caption ?? $taskName }}" class="w-full h-full object-cover">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                        <p class="text-gray-500 dark:text-gray-400">No staging photos assigned to this property.</p>
                    </div>
                @endif
            </div>

            {{-- Guides Tab --}}
            <div x-show="activeTab === 'guides'">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">3. Instructions & Guides</h3>
                
                @if(count($onboardingGuides) > 0)
                    <div class="space-y-4">
                        @foreach($onboardingGuides as $guide)
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
                                <h4 class="font-bold text-indigo-600 dark:text-indigo-400 text-lg mb-2">{{ $guide->task_name }}</h4>
                                <div class="prose dark:prose-invert max-w-none text-gray-600 dark:text-gray-300">
                                    {!! nl2br(e($guide->instructions)) !!}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                        <p class="text-gray-500 dark:text-gray-400">No specific instructions assigned to this property.</p>
                    </div>
                @endif
            </div>
            
            <div class="h-8"></div> {{-- Bottom padding --}}
        </div>

        {{-- Footer / Action --}}
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shrink-0 flex justify-between items-center">
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300" x-text="footerMessage"></p>
            </div>
            <div class="flex gap-3">
                <x-button type="button" @click="nextStep()" class="bg-indigo-600 hover:bg-indigo-700" x-bind:disabled="!canProceed">
                    <span x-text="isLastTab ? 'Finish & Start Session' : 'Continue'"></span>
                </x-button>
            </div>
        </div>
    </div>
</x-modal>

<script>
    window.onboardingFlow = function() {
        return {
            activeTab: 'videos',
            tabs: [
                { id: 'videos', completed: false, unlocked: true },
                { id: 'photos', completed: false, unlocked: false },
                { id: 'guides', completed: false, unlocked: false }
            ],
            videoStatus: {},
            totalVideos: {{ count($onboardingVideos) }},
            totalPhotos: {{ count($onboardingPhotos) }},
            totalGuides: {{ count($onboardingGuides) }},
            
            init() {
                // Pre-complete tabs that have no content
                if (this.totalVideos === 0) {
                    this.completeTab('videos');
                    this.activeTab = 'photos';
                    this.tabs[1].unlocked = true;
                } else {
                    // Set up video listeners
                    this.$nextTick(() => {
                        const videos = document.querySelectorAll('video');
                        videos.forEach(v => {
                            // Only complete when the video actually ends
                            v.addEventListener('ended', (e) => {
                                const id = e.target.id.replace('video-', '');
                                this.videoStatus[id] = true;
                                this.checkVideoCompletion();
                            });
                            
                            // Prevent scrubbing to the end to bypass watching
                            v.addEventListener('timeupdate', (e) => {
                                // If they skip to the end, we can pause or just let ended fire
                                // For basic compliance, relying on 'ended' is the primary fix.
                            });
                        });
                    });
                }
                
                if (this.totalPhotos === 0) {
                    this.completeTab('photos');
                    if (this.activeTab === 'photos') {
                        this.activeTab = 'guides';
                        this.tabs[2].unlocked = true;
                    }
                }
                
                if (this.totalGuides === 0) {
                    this.completeTab('guides');
                }
                
                // Watch for tab changes to reset scroll state if needed
                this.$watch('activeTab', value => {
                    this.$refs.scrollContainer.scrollTop = 0;
                    this.$nextTick(() => {
                        this.checkScroll();
                    });
                });

                // Also check on initial load in case the first active tab is short
                this.$nextTick(() => {
                    this.checkScroll();
                });
            },
            
            playVideo(id) {
                const video = document.getElementById('video-' + id);
                if (video) {
                    video.play().catch(e => console.log('Autoplay prevented', e));
                    // Removed: this.videoStatus[id] = true;
                    // Removed: this.checkVideoCompletion();
                }
            },
            
            checkVideoCompletion() {
                const playedCount = Object.values(this.videoStatus).filter(Boolean).length;
                if (playedCount >= this.totalVideos) {
                    this.completeTab('videos');
                }
            },
            
            checkScroll() {
                if (this.activeTab === 'videos') return; // Videos are completed by playing
                
                const container = this.$refs.scrollContainer;
                // If scrolled near bottom or content is small enough to not need scrolling
                if (container.scrollTop + container.clientHeight >= container.scrollHeight - 50) {
                    this.completeTab(this.activeTab);
                }
            },
            
            completeTab(tabId) {
                const tabIndex = this.tabs.findIndex(t => t.id === tabId);
                if (tabIndex > -1) {
                    this.tabs[tabIndex].completed = true;
                    // Unlock next tab
                    if (tabIndex < this.tabs.length - 1) {
                        this.tabs[tabIndex + 1].unlocked = true;
                    }
                }
            },
            
            get canProceed() {
                const currentTab = this.tabs.find(t => t.id === this.activeTab);
                return currentTab ? currentTab.completed : false;
            },
            
            get isLastTab() {
                return this.activeTab === 'guides';
            },
            
            get footerMessage() {
                if (!this.canProceed) {
                    if (this.activeTab === 'videos') return 'Please play all videos to continue.';
                    return 'Please scroll through all items to continue.';
                }
                return 'Great! You can proceed.';
            },
            
            nextStep() {
                if (!this.canProceed) return;
                
                const tabIndex = this.tabs.findIndex(t => t.id === this.activeTab);
                if (tabIndex < this.tabs.length - 1) {
                    this.activeTab = this.tabs[tabIndex + 1].id;
                } else {
                    this.finishOnboarding();
                }
            },
            
            finishOnboarding() {
                // Submit AJAX to complete
                fetch("{{ route('sessions.complete-onboarding', $session) }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                }).then(() => {
                    // Close modal and start session
                    this.$dispatch('close');
                    document.getElementById('gps-start').submit();
                });
            }
        };
    };
</script>
@endif
