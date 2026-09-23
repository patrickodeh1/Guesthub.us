<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-gray-900 dark:text-gray-100">
                    {{ auth()->user()->hasAnyRole(['admin', 'owner', 'company']) ? 'All Assignments' : 'My Assignments' }}
                </h2>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ auth()->user()->hasAnyRole(['admin', 'owner', 'company']) 
                        ? 'View and manage all scheduled cleaning sessions grouped by date.' 
                        : 'View and manage your scheduled cleaning sessions grouped by date.' }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-6 px-1 sm:px-0">
        {{-- Filtering Tabs --}}
        <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-none">
            <a href="{{ route('assignments.index', ['filter' => 'all']) }}" 
               class="px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-all duration-150 shadow-sm border
               {{ $selectedFilter === 'all' 
                  ? 'bg-blue-600 border-blue-600 text-white dark:bg-blue-500 dark:border-blue-500' 
                  : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                All Assignments
            </a>
            <a href="{{ route('assignments.index', ['filter' => 'today']) }}" 
               class="px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-all duration-150 shadow-sm border
               {{ $selectedFilter === 'today' 
                  ? 'bg-blue-600 border-blue-600 text-white dark:bg-blue-500 dark:border-blue-500' 
                  : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                Today Only
            </a>
            <a href="{{ route('assignments.index', ['filter' => 'upcoming']) }}" 
               class="px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-all duration-150 shadow-sm border
               {{ $selectedFilter === 'upcoming' 
                  ? 'bg-blue-600 border-blue-600 text-white dark:bg-blue-500 dark:border-blue-500' 
                  : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                Upcoming
            </a>
            <a href="{{ route('assignments.index', ['filter' => 'overdue']) }}" 
               class="px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-all duration-150 shadow-sm border
               {{ $selectedFilter === 'overdue' 
                  ? 'bg-blue-600 border-blue-600 text-white dark:bg-blue-500 dark:border-blue-500' 
                  : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700' }}">
                Overdue / Pending
            </a>
        </div>

        {{-- Grouped List --}}
        <div class="space-y-4">
            @forelse($groupedAssignments as $dateLabel => $sessions)
                <div x-data="{ open: true }" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden transition-all duration-200">
                    <!-- Sticky Date Group Header -->
                    <div @click="open = !open" 
                         class="sticky top-0 bg-gray-50 dark:bg-gray-800/50 backdrop-blur px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between cursor-pointer select-none hover:bg-gray-100/70 dark:hover:bg-gray-700/50 transition-colors z-10">
                        <div class="flex items-center gap-2.5">
                            <span class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                <span>{{ $dateLabel }}</span>
                            </span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                {{ count($sessions) }} {{ Str::plural('job', count($sessions)) }}
                            </span>
                        </div>
                        <div class="text-gray-400 dark:text-gray-500">
                            <svg class="w-5 h-5 transform transition-transform duration-200" 
                                 :class="open ? 'rotate-180' : ''" 
                                 fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </div>

                    <!-- Assignments List -->
                    <div x-show="open" x-collapse>
                        <div class="divide-y divide-gray-150 dark:divide-gray-700">
                            @foreach($sessions as $session)
                                <a href="{{ route('sessions.show', $session) }}" 
                                   class="block p-5 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-all active:scale-[0.99] group">
                                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                        <div class="space-y-1.5 min-w-0 flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <h3 class="font-bold text-base text-gray-900 dark:text-gray-100 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                                    {{ $session->property->name }}
                                                </h3>
                                                @if(auth()->user()->hasAnyRole(['admin', 'owner', 'company']) && $session->housekeeper)
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                        <svg class="w-3 h-3 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                        </svg>
                                                        <span>{{ $session->housekeeper->name }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                            
                                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 font-medium flex items-center gap-2 flex-wrap">
                                                <span class="inline-flex items-center gap-1">
                                                    <x-icons.rooms class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" />
                                                    <span>{{ $session->property->rooms_count ?? 0 }} {{ Str::plural('room', $session->property->rooms_count ?? 0) }}</span>
                                                </span>
                                                <span class="text-gray-300 dark:text-gray-600">·</span>
                                                <span class="inline-flex items-center gap-1">
                                                    <x-icons.tasks class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" />
                                                    <span>{{ $session->property->property_tasks_count ?? 0 }} {{ Str::plural('task', $session->property->property_tasks_count ?? 0) }}</span>
                                                </span>
                                            </p>

                                            <div class="flex items-center gap-3.5 pt-1 text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                                                <span class="flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span>{{ $session->scheduled_time ? $session->scheduled_time->format('g:i A') : 'No time specified' }}</span>
                                                </span>
                                                @if($session->property->address)
                                                    <span class="truncate max-w-xs sm:max-w-md hidden sm:inline-flex items-center gap-1">
                                                        <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                        </svg>
                                                        <span>{{ $session->property->address }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-start gap-3 flex-shrink-0">
                                            <x-status-badge :status="$session->status" />
                                            
                                            <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 group-hover:translate-x-1 transition-transform flex items-center gap-1 select-none">
                                                Open Job 
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                                                </svg>
                                            </span>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-8 text-center text-gray-500 dark:text-gray-400 shadow-sm">
                    <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                    </svg>
                    <h3 class="font-bold text-gray-900 dark:text-gray-100">No assignments found</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-xs mx-auto">
                        There are no jobs matching the selected filter in your schedule.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
