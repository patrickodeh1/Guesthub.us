<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">Photos</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Staging & reference images for your properties</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6" x-data="photoGallery()">
        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4 sm:p-5">
            <form method="GET" action="{{ route('resources.photos') }}" class="flex flex-col sm:flex-row gap-3 items-end">
                <div class="flex-1 min-w-0">
                    <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1.5">Filter by Property</label>
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
                @if($selectedPropertyId)
                    <a href="{{ route('resources.photos') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                        Clear
                    </a>
                @endif
            </form>
        </div>

        {{-- Content --}}
        @if($grouped->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5a1.5 1.5 0 001.5-1.5V5.25a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5v14.25c0 .828.672 1.5 1.5 1.5z"></path>
                </svg>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">No photos found</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">There are no staging or reference images for your properties yet.</p>
            </div>
        @else
            @foreach($grouped as $propertyName => $photos)
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    {{-- Property Header --}}
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-purple-50 to-transparent dark:from-purple-900/10 dark:to-transparent">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-purple-500 flex-shrink-0"></span>
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">{{ $propertyName }}</h3>
                            <span class="ml-auto text-xs font-semibold text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-full">
                                {{ $photos->count() }} {{ Str::plural('photo', $photos->count()) }}
                            </span>
                        </div>
                    </div>

                    {{-- Photo Grid --}}
                    <div class="p-4 sm:p-5">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                            @foreach($photos as $photo)
                                <button type="button"
                                        @click="openGallery('{{ $photo->resolved_url }}')"
                                        class="group relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900 aspect-square hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                                    <img src="{{ $photo->resolved_thumbnail }}"
                                         alt="{{ $photo->media_caption ?: $photo->task_name }}"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                         loading="lazy"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\' viewBox=\'0 0 200 200\'%3E%3Crect width=\'100%25\' height=\'100%25\' fill=\'%23f1f5f9\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' dominant-baseline=\'middle\' text-anchor=\'middle\' font-family=\'sans-serif\' font-size=\'12\' fill=\'%2394a3b8\'%3ENo Preview%3C/text%3E%3C/svg%3E';" />
                                    {{-- Overlay with task/room info --}}
                                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent px-2 py-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                        <p class="text-[10px] font-bold text-white/80 truncate">{{ $photo->room_name }}</p>
                                        <p class="text-[10px] text-white/60 truncate">{{ $photo->task_name }}</p>
                                    </div>
                                    {{-- Zoom icon --}}
                                    <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                        <span class="flex items-center justify-center w-7 h-7 bg-black/50 rounded-full backdrop-blur-sm">
                                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path></svg>
                                        </span>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        @endif

        {{-- Lightbox --}}
        <div x-show="lightboxOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[300] bg-black/90 backdrop-blur-sm flex items-center justify-center p-4"
             @click.self="lightboxOpen = false"
             @keydown.escape.window="lightboxOpen = false">
            <img :src="lightboxSrc" class="max-h-[90vh] max-w-[90vw] rounded-lg shadow-2xl object-contain" alt="Full size photo" />
            <button type="button" @click="lightboxOpen = false"
                    class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 transition-colors bg-black/30 rounded-full w-10 h-10 flex items-center justify-center backdrop-blur-sm">&times;</button>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('photoGallery', () => ({
                lightboxOpen: false,
                lightboxSrc: '',
                openGallery(src) {
                    this.lightboxSrc = src;
                    this.lightboxOpen = true;
                }
            }));
        });
    </script>
</x-app-layout>
