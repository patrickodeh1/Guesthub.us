<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center justify-between">
            <div class="min-w-0">
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">Videos</h2>
                <p class="mt-1 break-words text-sm text-gray-600 dark:text-gray-400">Instructional & training videos for your properties</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6" x-data="videoPlayer()">
        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4 sm:p-5">
            <form method="GET" action="{{ route('resources.videos') }}" class="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:flex">
                <div class="min-w-0 lg:flex-1">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Property</label>
                    <select name="property" onchange="this.form.submit()"
                            class="w-full min-w-0 text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">All Properties</option>
                        @foreach($properties as $property)
                            <option value="{{ $property->id }}" {{ $selectedPropertyId == $property->id ? 'selected' : '' }}>
                                {{ $property->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0 lg:flex-1">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Category</label>
                    <select name="category" onchange="this.form.submit()"
                            class="w-full min-w-0 text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2.5 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
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
                       class="inline-flex min-h-11 w-full items-center justify-center gap-1.5 rounded-lg bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:hover:bg-gray-600 sm:col-span-2 lg:w-auto lg:flex-none">
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
                         class="min-w-0 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden group cursor-pointer hover:shadow-md hover:border-purple-300 dark:hover:border-purple-700 transition-all duration-200">
                        {{-- Thumbnail --}}
                        <div class="relative aspect-video bg-gray-950 overflow-hidden">
                            @if ($video->thumbnail_url)
                                <img src="{{ $video->thumbnail_url }}"
                                     alt="{{ $video->title }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                     loading="lazy" />
                            @else
                                <video src="{{ $video->video_url }}#t=0.5" preload="metadata" muted playsinline
                                       class="w-full h-full object-cover pointer-events-none"></video>
                            @endif
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
                            <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100 min-w-0 break-words group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">
                                {{ $video->title }}
                            </h4>
                            @if($video->description)
                                <p class="mt-1 break-words text-xs text-gray-500 dark:text-gray-400">{{ $video->description }}</p>
                            @endif
                            <div class="mt-2.5 flex items-center gap-2 flex-wrap">
                                <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400">
                                    {{ str_replace('-', ' ', $video->category) }}
                                </span>
                                @if($video->properties->isNotEmpty())
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 min-w-0 break-words max-w-[120px]" title="{{ $video->properties->pluck('name')->join(', ') }}">
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
                 class="fixed inset-0 z-[300] flex flex-col items-center justify-start overflow-y-auto bg-black/95 p-3 backdrop-blur-md sm:justify-center sm:p-6 md:p-8"
                 @click.self="closePlayer()"
                 @keydown.escape.window="closePlayer()">
                <div class="relative flex aspect-video max-h-[58vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-gray-800 bg-black shadow-2xl sm:max-h-[70vh] sm:rounded-2xl">
                    <video playsinline webkit-playsinline x-ref="videoPlayer" :src="activeVideo.video_url" controls preload="metadata"
                           class="w-full h-full object-contain focus:outline-none"></video>
                    <button type="button" @click="closePlayer()"
                            class="absolute right-2 top-2 z-50 rounded-full bg-black/60 p-2 text-white shadow-md transition-all hover:scale-105 hover:bg-black/80 focus:outline-none sm:right-4 sm:top-4"
                            aria-label="Close Player">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="mt-3 w-full max-w-4xl px-1 text-left text-white sm:mt-4 sm:px-2">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="px-2 py-0.5 text-xs font-semibold rounded bg-purple-600 text-white uppercase"
                              x-text="activeVideo.category ? activeVideo.category.replace('-', ' ') : ''"></span>
                        <template x-if="activeVideo.duration_seconds">
                            <span class="text-xs text-gray-400"
                                  x-text="Math.floor(activeVideo.duration_seconds / 60) + 'm ' + (activeVideo.duration_seconds % 60) + 's'"></span>
                        </template>
                    </div>
                    <h3 class="mt-2 break-words text-lg font-bold text-gray-100 sm:text-xl" x-text="activeVideo.title"></h3>
                    <p class="mt-1 break-words text-sm text-gray-400" x-text="activeVideo.description || ''"></p>
                </div>
            </div>
        </template>
    </div>

    <script>
        function videoPlayer() {
            return ({
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
            });
        }
    </script>
</x-app-layout>
