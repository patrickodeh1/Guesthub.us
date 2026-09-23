<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">Guides</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Task instructions & guidelines for your properties</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Filters --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4 sm:p-5">
            <form method="GET" action="{{ route('resources.guides') }}" class="flex flex-col sm:flex-row gap-3 items-end">
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
                    <a href="{{ route('resources.guides') }}"
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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-1">No guides found</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">There are no task instructions available for your properties yet.</p>
            </div>
        @else
            @foreach($grouped as $propertyName => $rooms)
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    {{-- Property Header --}}
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-indigo-50 to-transparent dark:from-indigo-900/10 dark:to-transparent">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"></span>
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">{{ $propertyName }}</h3>
                        </div>
                    </div>

                    {{-- Rooms / Sections --}}
                    <div class="divide-y divide-gray-100 dark:divide-gray-700/50" x-data="{ openSections: {} }">
                        @foreach($rooms as $roomName => $guides)
                            <div class="group">
                                {{-- Room/Section Header (Accordion Toggle) --}}
                                <button type="button"
                                        @click="openSections['{{ md5($propertyName . $roomName) }}'] = !openSections['{{ md5($propertyName . $roomName) }}']"
                                        class="w-full flex items-center justify-between px-5 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $roomName === 'Property-Level' ? 'bg-emerald-500' : 'bg-blue-500' }}"></span>
                                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $roomName }}</span>
                                        <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">
                                            {{ $guides->count() }} {{ Str::plural('task', $guides->count()) }}
                                        </span>
                                    </div>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200"
                                         :class="openSections['{{ md5($propertyName . $roomName) }}'] ? 'rotate-180' : ''"
                                         fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                {{-- Task Instructions (Expandable) --}}
                                <div x-show="openSections['{{ md5($propertyName . $roomName) }}']"
                                     x-cloak
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 -translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     class="px-5 pb-4 space-y-3">
                                    @foreach($guides as $guide)
                                        <div class="rounded-xl border {{ $guide->task_type === 'instructions' ? 'border-amber-200/50 dark:border-amber-800/30 bg-amber-50/30 dark:bg-amber-950/10' : 'border-gray-200/50 dark:border-gray-700/50 bg-gray-50/50 dark:bg-gray-800/30' }} p-4">
                                            <div class="flex items-start gap-2 mb-2">
                                                @if($guide->task_type === 'instructions')
                                                    <svg class="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                                    </svg>
                                                @else
                                                    <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                @endif
                                                <h5 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $guide->task_name }}</h5>
                                                @if($guide->task_type === 'instructions')
                                                    <span class="ml-auto text-[9px] font-bold uppercase px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 flex-shrink-0">Read-Only</span>
                                                @endif
                                            </div>
                                            <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line break-words pl-6">{{ $guide->instructions }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-app-layout>
