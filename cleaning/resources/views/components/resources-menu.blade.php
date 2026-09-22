@props(['propertyId'])

<div x-data="resourcesMenu()" class="relative">
    <!-- Floating Resources Button -->
    <div class="fixed bottom-24 right-6 z-45">
        <button 
            type="button"
            @click="open({{ $propertyId }})"
            class="flex items-center justify-center w-14 h-14 bg-purple-600 hover:bg-purple-700 dark:bg-purple-500 dark:hover:bg-purple-600 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 hover:scale-110 active:scale-95 group relative"
            aria-label="View Training Videos"
        >
            <!-- Video Camera / Library Icon -->
            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
            </svg>
            <!-- Hover Tooltip -->
            <span class="absolute bottom-full right-0 mb-2 hidden group-hover:block bg-gray-900 dark:bg-gray-700 text-white text-xs font-semibold px-2 py-1 rounded shadow-md whitespace-nowrap">
                Resources Menu
            </span>
        </button>
    </div>

    <!-- Sidebar Slide-over Drawer -->
    <div 
        x-show="isOpen" 
        x-cloak 
        class="fixed inset-0 overflow-hidden z-[200]" 
        aria-labelledby="slide-over-title" 
        role="dialog" 
        aria-modal="true"
    >
        <div class="absolute inset-0 overflow-hidden">
            <!-- Backdrop -->
            <div 
                x-show="isOpen"
                x-transition:enter="ease-in-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in-out duration-300"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0 bg-black/60 backdrop-blur-sm transition-opacity" 
                @click="close()"
            ></div>

            <!-- Drawer Container -->
            <div class="absolute inset-y-0 right-0 pl-10 max-w-full flex">
                <div 
                    x-show="isOpen"
                    x-transition:enter="transform transition ease-in-out duration-300"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition ease-in-out duration-300"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="w-screen max-w-md"
                >
                    <div class="h-full flex flex-col bg-white dark:bg-gray-950 shadow-2xl border-l border-gray-200 dark:border-gray-800">
                        <!-- Drawer Header -->
                        <div class="px-6 py-5 bg-purple-600 dark:bg-purple-900 text-white flex items-center justify-start gap-4">
                            <button type="button" @click="close()" class="rounded-md text-purple-100 hover:text-white focus:outline-none p-1 transition-colors bg-purple-800/50 hover:bg-purple-800">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                            <div class="flex items-center gap-2">
                                <svg class="w-6 h-6 hidden sm:block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                                <h2 class="text-lg font-bold" id="slide-over-title">Property Video Resources</h2>
                            </div>
                        </div>

                        <!-- Category Filter Tabs -->
                        <div class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 px-4 py-2 overflow-x-auto flex gap-1 scrollbar-none">
                            <button 
                                type="button" 
                                @click="activeCategory = ''"
                                :class="activeCategory === '' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                                class="px-3 py-1.5 text-xs rounded-lg whitespace-nowrap transition-colors"
                            >
                                All
                            </button>
                            <button 
                                type="button" 
                                @click="activeCategory = 'general'"
                                :class="activeCategory === 'general' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                                class="px-3 py-1.5 text-xs rounded-lg whitespace-nowrap transition-colors"
                            >
                                General
                            </button>
                            <button 
                                type="button" 
                                @click="activeCategory = 'room-specific'"
                                :class="activeCategory === 'room-specific' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                                class="px-3 py-1.5 text-xs rounded-lg whitespace-nowrap transition-colors"
                            >
                                Rooms
                            </button>
                            <button 
                                type="button" 
                                @click="activeCategory = 'equipment'"
                                :class="activeCategory === 'equipment' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                                class="px-3 py-1.5 text-xs rounded-lg whitespace-nowrap transition-colors"
                            >
                                Equipment
                            </button>
                            <button 
                                type="button" 
                                @click="activeCategory = 'safety'"
                                :class="activeCategory === 'safety' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                                class="px-3 py-1.5 text-xs rounded-lg whitespace-nowrap transition-colors"
                            >
                                Safety
                            </button>
                            <button 
                                type="button" 
                                @click="activeCategory = 'process'"
                                :class="activeCategory === 'process' ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 font-semibold' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'"
                                class="px-3 py-1.5 text-xs rounded-lg whitespace-nowrap transition-colors"
                            >
                                Process
                            </button>
                        </div>

                        <!-- Drawer Body (Video List) -->
                        <div class="flex-1 overflow-y-auto p-5 space-y-4">
                            <!-- Loading State -->
                            <div x-show="loading" class="flex flex-col items-center justify-center py-12 text-gray-500 dark:text-gray-400">
                                <svg class="animate-spin h-8 w-8 text-purple-600 dark:text-purple-400 mb-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-sm font-medium">Loading resources...</span>
                            </div>

                            <!-- Error State -->
                            <div x-show="error" class="text-center p-6 border border-red-200 dark:border-red-800/40 bg-red-50 dark:bg-red-950/20 rounded-2xl">
                                <p class="text-sm text-red-700 dark:text-red-400 font-semibold mb-3" x-text="error"></p>
                                <x-button variant="secondary" @click="fetchVideos({{ $propertyId }})" class="!text-xs">Retry</x-button>
                            </div>

                            <!-- Empty State -->
                            <div x-show="!loading && !error && filteredVideos.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                                <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"></path>
                                </svg>
                                <h4 class="font-bold text-gray-900 dark:text-gray-200">No videos available</h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-[240px] mx-auto">
                                    No videos match the selected category for this property.
                                </p>
                            </div>

                            <!-- Video Grid -->
                            <div x-show="!loading && !error && filteredVideos.length > 0" class="space-y-4">
                                <template x-for="video in filteredVideos" :key="video.id">
                                    <div 
                                        @click="playVideo(video)"
                                        class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800/80 overflow-hidden shadow-sm hover:shadow-md cursor-pointer group transition-all duration-200 flex"
                                    >
                                        <!-- Thumbnail -->
                                        <div class="relative w-28 sm:w-32 aspect-video bg-gray-950 flex-shrink-0 flex items-center justify-center overflow-hidden border-r border-gray-100 dark:border-gray-800">
                                            <img :src="video.thumbnail_url" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" />
                                            
                                            <!-- Duration badge -->
                                            <template x-if="video.duration_seconds">
                                                <span class="absolute bottom-1 right-1 px-1 py-0.2 text-[10px] font-bold rounded bg-black/75 text-white" x-text="Math.floor(video.duration_seconds / 60) + ':' + String(video.duration_seconds % 60).padStart(2, '0')"></span>
                                            </template>

                                            <!-- Play icon overlay -->
                                            <div class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/35 transition-colors">
                                                <span class="w-7 h-7 flex items-center justify-center rounded-full bg-white/95 text-purple-600 shadow transition-transform group-hover:scale-110">
                                                    <svg class="w-3.5 h-3.5 fill-current ml-0.5" viewBox="0 0 24 24">
                                                        <path d="M8 5v14l11-7z"/>
                                                    </svg>
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Details -->
                                        <div class="p-3 flex-1 min-w-0 flex flex-col justify-center">
                                            <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100 truncate group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors" x-text="video.title"></h4>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2" x-text="video.description || 'No description provided.'"></p>
                                            <div class="mt-1.5 flex items-center gap-1.5">
                                                <span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400" x-text="video.category.replace('-', ' ')"></span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Video Player Overlay Modal -->
    <template x-if="activeVideo">
        <div 
            x-show="activeVideo"
            class="fixed inset-0 z-[300] bg-black/95 backdrop-blur-md flex flex-col justify-center items-center p-4 sm:p-6 md:p-8"
            @click.self="closePlayer()"
        >
            <!-- Player Container -->
            <div class="w-full max-w-4xl aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl relative flex flex-col border border-gray-800">
                <!-- Video tag -->
                <video 
                    x-ref="videoPlayer" 
                    :src="activeVideo.video_url" 
                    controls
                    playsinline
                    preload="metadata"
                    class="w-full h-full object-contain focus:outline-none"
                ></video>

                <!-- Close trigger -->
                <button 
                    type="button" 
                    @click="closePlayer()" 
                    class="absolute top-4 right-4 bg-black/60 hover:bg-black/80 text-white rounded-full p-2 hover:scale-105 transition-all shadow-md z-50 focus:outline-none"
                    aria-label="Close Player"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Metadata Box -->
            <div class="w-full max-w-4xl mt-4 text-left text-white px-2">
                <div class="flex items-center gap-3">
                    <span class="px-2 py-0.5 text-xs font-semibold rounded bg-purple-600 text-white uppercase" x-text="activeVideo.category.replace('-', ' ')"></span>
                    <template x-if="activeVideo.duration_seconds">
                        <span class="text-xs text-gray-400" x-text="Math.floor(activeVideo.duration_seconds / 60) + 'm ' + (activeVideo.duration_seconds % 60) + 's'"></span>
                    </template>
                </div>
                <h3 class="text-lg sm:text-xl font-bold mt-2 text-gray-100" x-text="activeVideo.title"></h3>
                <p class="text-sm text-gray-400 mt-1" x-text="activeVideo.description || ''"></p>
            </div>
        </div>
    </template>
</div>
