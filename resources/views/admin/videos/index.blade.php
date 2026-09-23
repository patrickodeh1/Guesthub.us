<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-1 sm:px-0">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Instructional Videos
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage centralized instructional videos and assign them to properties.
                </p>
            </div>
            <x-button href="{{ route('admin.videos.create') }}"
                class="inline-flex items-center justify-center px-4 py-2 rounded bg-indigo-600 hover:bg-indigo-700 text-white w-full sm:w-auto whitespace-nowrap">
                <svg class="w-5 h-5 mr-1.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                </svg>
                Upload Video
            </x-button>
        </div>
    </x-slot>

    <div class="space-y-6 w-full max-w-full">
        {{-- Success/Status Message --}}
        @if (session('success'))
            <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 dark:bg-gray-800 dark:text-green-400 border border-green-200 dark:border-green-800" role="alert">
                {{ session('success') }}
            </div>
        @endif

        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <form method="get" class="flex flex-col sm:flex-row flex-wrap gap-3 w-full">
                <div class="flex-1 min-w-[200px]">
                    <x-form.input name="q" type="text" :value="request('q')" autocomplete="off"
                        placeholder="Search videos..." class="w-full" />
                </div>

                <div class="w-full sm:w-auto sm:min-w-[160px]">
                    <x-form.select name="category" :selected="request('category')" class="w-full !py-1">
                        <option value="">All Categories</option>
                        <option value="general" @selected(request('category') === 'general')>General Guidelines</option>
                        <option value="room-specific" @selected(request('category') === 'room-specific')>Room Specific</option>
                        <option value="equipment" @selected(request('category') === 'equipment')>Equipment</option>
                        <option value="safety" @selected(request('category') === 'safety')>Safety</option>
                        <option value="process" @selected(request('category') === 'process')>Process</option>
                    </x-form.select>
                </div>

                <div class="flex gap-2 w-full sm:w-auto">
                    <x-button variant="secondary" class="flex-1 sm:flex-none justify-center whitespace-nowrap">Filter</x-button>
                    <x-button variant="secondary" :href="route('admin.videos.index')" class="flex-1 sm:flex-none justify-center whitespace-nowrap">Clear</x-button>
                </div>
            </form>
        </div>

        {{-- Video Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($videos as $video)
                <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col group hover:shadow-lg transition-all duration-300">
                    {{-- Thumbnail & Duration --}}
                    <div class="relative aspect-video bg-gray-950 flex items-center justify-center overflow-hidden">
                        @if ($video->thumbnail_path)
                            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                        @else
                            {{-- Fallback: Use the video itself to show the first frame --}}
                            <video src="{{ $video->video_url }}#t=0.1" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" preload="metadata" muted playsinline></video>
                        @endif

                        {{-- Category Badge --}}
                        <span class="absolute top-3 left-3 px-2 py-1 text-xs font-semibold rounded bg-black/60 text-white backdrop-blur-sm uppercase">
                            {{ str_replace('-', ' ', $video->category) }}
                        </span>

                        {{-- Play Button Overlay --}}
                        <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/45 transition-colors duration-300">
                            <span class="w-12 h-12 flex items-center justify-center rounded-full bg-white/90 text-indigo-600 shadow-md group-hover:scale-110 active:scale-95 transition-transform duration-200">
                                <svg class="w-6 h-6 fill-current ml-0.5" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </span>
                        </div>

                        {{-- Duration Overlay --}}
                        @if ($video->duration_seconds)
                            <span class="absolute bottom-3 right-3 px-2 py-0.5 text-xs font-semibold rounded bg-black/75 text-white">
                                {{ sprintf('%02d:%02d', floor($video->duration_seconds / 60), $video->duration_seconds % 60) }}
                            </span>
                        @endif

                        {{-- Processing Overlay --}}
                        @if (isset($video->processing_status) && $video->processing_status === 'processing')
                            <div class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-gray-900/80 backdrop-blur-sm text-white">
                                <svg class="animate-spin h-8 w-8 mb-2 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-sm font-medium tracking-wide">Processing Video...</span>
                            </div>
                        @elseif(isset($video->processing_status) && $video->processing_status === 'failed')
                            <div class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-red-900/80 backdrop-blur-sm text-white">
                                <svg class="w-8 h-8 mb-2 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <span class="text-sm font-medium tracking-wide">Processing Failed</span>
                            </div>
                        @endif
                    </div>

                    {{-- Body --}}
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div class="space-y-2">
                            <h3 class="font-bold text-lg text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 transition-colors line-clamp-1">
                                {{ $video->title }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 min-h-[40px]">
                                {{ $video->description ?: 'No description provided.' }}
                            </p>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                {{ $video->properties_count }} Property/ies
                            </span>

                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                {{ $video->creator->name }}
                            </span>
                        </div>
                    </div>

                    {{-- Actions Footer --}}
                    <div class="px-5 py-4 bg-gray-50 dark:bg-gray-800/40 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                        {{-- Publish Toggle Form --}}
                        <form method="post" action="{{ route('admin.videos.publish', $video) }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition-colors
                                {{ $video->is_published
                                    ? 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-400'
                                    : 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                <span class="w-2 h-2 rounded-full {{ $video->is_published ? 'bg-green-500' : 'bg-amber-500' }}"></span>
                                {{ $video->is_published ? 'Published' : 'Draft' }}
                            </button>
                        </form>

                        {{-- Edit/Delete --}}
                        <div class="flex items-center gap-1">
                            <x-button variant="secondary" class="!px-2.5 !py-1.5 text-xs inline-flex items-center gap-1"
                                href="{{ route('admin.videos.edit', $video) }}">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                                </svg>
                                Edit
                            </x-button>

                            <form method="post" action="{{ route('admin.videos.destroy', $video) }}"
                                onsubmit="return confirm('Are you sure you want to permanently delete this video and its files? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                    class="inline-flex items-center justify-center p-2 rounded-lg text-red-600 hover:bg-red-50 dark:hover:bg-red-950/30 transition-colors"
                                    aria-label="Delete Video">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 py-12 px-4 text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z"></path>
                    </svg>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">No videos found</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-sm mx-auto">
                        Get started by uploading your first housekeeping instructional video.
                    </p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div>
            {{ $videos->links() }}
        </div>
    </div>
</x-app-layout>
