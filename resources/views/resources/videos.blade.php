<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">Videos</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Instructional & training videos for your properties</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6" x-data="videoPlayer()">
        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4 sm:p-5">
            <form method="GET" action="{{ route('resources.videos') }}" class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Property</label>
                    <select name="property" onchange="this.form.submit()"
                            class="w-full text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">All Properties</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" {{ $selectedPropertyId == $property->id ? 'selected' : '' }}>
                                {{ $property->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Category</label>
                    <select name="category" onchange="this.form.submit()"
                            class="w-full text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ $selectedCategory === $cat ? 'selected' : '' }}>
                                {{ ucwords(str_replace('-', ' ', $cat)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if($selectedPropertyId || $selectedCategory)
                    <a href="{{ route('resources.videos') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                        Clear
                    </a>
                @endif
            </form>
        </div>

        {{-- Content --}}
        @if($videos->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"></path>
                </svg>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">No videos found</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">There are no instructional videos available for your properties.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($videos as $video)
                    <div @click="play(@js($video->toArray()))"
                         class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden group cursor-pointer hover:shadow-md hover:border-purple-300 dark:hover:border-purple-700 transition-all duration-200">
                        {{-- Thumbnail --}}
                        <div class="relative aspect-video bg-gray-950 overflow-hidden">
                            <img src="{{ $video->thumbnail_url }}"
                                 alt="{{ $video->title }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                 loading="lazy" />
                            {{-- Play overlay --}}
                            <div class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/35 transition-colors">
                                <span class="w-12 h-12 flex items-center justify-center rounded-full bg-white/90 text-purple-600 shadow-lg transition-transform group-hover:scale-110">
                                    <svg class="w-5 h-5 fill-current ml-0.5" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                </span>
                            </div>
                            {{-- Duration --}}
                            @if($video->duration_seconds)
                                <span class="absolute bottom-2 right-2 px-1.5 py-0.5 text-[10px] font-bold rounded bg-black/75 text-white">
                                    {{ floor($video->duration_seconds / 60) }}:{{ str_pad($video->duration_seconds % 60, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @endif
                        </div>

                        {{-- Info --}}
                        <div class="p-4">
                            <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100 truncate group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">
                                {{ $video->title }}
                            </h4>
                            @if($video->description)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">{{ $video->description }}</p>
                            @endif
                            <div class="mt-2.5 flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400">
                                    {{ str_replace('-', ' ', $video->category) }}
                                </span>
                                @if($video->properties->isNotEmpty())
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 truncate max-w-[120px]" title="{{ $video->properties->pluck('name')->join(', ') }}">
                                        {{ $video->properties->first()->name }}{{ $video->properties->count() > 1 ? ' +' . ($video->properties->count() - 1) : '' }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Video Player Modal --}}
        <template x-if="activeVideo">
            <div x-show="activeVideo"
                 class="fixed inset-0 z-[300] bg-black/95 backdrop-blur-md flex flex-col justify-center items-center p-4 sm:p-6 md:p-8"
                 @click.self="closePlayer()"
                 @keydown.escape.window="closePlayer()">
                <div class="w-full max-w-4xl aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl relative flex flex-col border border-gray-800">
                    <video x-ref="videoPlayer" :src="activeVideo.video_url" controls playsinline preload="metadata"
                           class="w-full h-full object-contain focus:outline-none"></video>
                    <button type="button" @click="closePlayer()"
                            class="absolute top-4 right-4 bg-black/60 hover:bg-black/80 text-white rounded-full p-2 hover:scale-105 transition-all shadow-md z-50 focus:outline-none"
                            aria-label="Close Player">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="w-full max-w-4xl mt-4 text-left text-white px-2">
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-purple-600 text-white uppercase"
                              x-text="activeVideo.category ? activeVideo.category.replace('-', ' ') : ''"></span>
                        <template x-if="activeVideo.duration_seconds">
                            <span class="text-xs text-gray-400"
                                  x-text="Math.floor(activeVideo.duration_seconds / 60) + 'm ' + (activeVideo.duration_seconds % 60) + 's'"></span>
                        </template>
                    </div>
                    <h3 class="text-lg sm:text-xl font-bold mt-2 text-gray-100" x-text="activeVideo.title"></h3>
                    <p class="text-sm text-gray-400 mt-1" x-text="activeVideo.description || ''"></p>
                </div>
            </div>
        </template>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('videoPlayer', () => ({
                activeVideo: null,
                play(video) {
                    this.activeVideo = video;
                    document.body.classList.add('overflow-hidden');
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
                    if (videoEl) videoEl.pause();
                    this.activeVideo = null;
                    document.body.classList.remove('overflow-hidden');
                }
            }));
        });
    </script>
</x-app-layout>
