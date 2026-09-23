<x-app-layout>
    @php
        $dateFormat = \App\Models\Setting::get('date_format', 'M d, Y');
    @endphp
    <x-slot name="header">
        <h2 class="text-lg sm:text-xl font-semibold">Manage Assignment</h2>
    </x-slot>

    <x-card class="mb-4">
        <form method="get" class="space-y-4 px-1 sm:px-0">
            <div class="flex flex-wrap gap-4">
                <div class="w-full sm:w-auto sm:flex-1 sm:min-w-[140px]">
                    <x-form.label value="Property" />
                    <x-form.select name="property_id" class="!py-1 w-full rounded border-gray-300">
                        <option value="">All</option>
                        @foreach ($properties as $p)
                            <option value="{{ $p->id }}" @selected($filters['property_id'] == $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </x-form.select>
                </div>
                <div class="w-full sm:w-auto sm:flex-1 sm:min-w-[140px]">
                    <x-form.label value="Housekeeper" />
                    <x-form.select name="housekeeper_id" class="!py-1 w-full rounded border-gray-300">
                        <option value="">All</option>
                        @foreach ($housekeepers as $hk)
                            <option value="{{ $hk->id }}" @selected($filters['housekeeper_id'] == $hk->id)>{{ $hk->name }}</option>
                        @endforeach
                    </x-form.select>
                </div>
                <div class="w-full sm:w-auto sm:flex-1 sm:min-w-[120px]">
                    <x-form.label value="Status" />
                    <x-form.select name="status" class="!py-1 w-full rounded border-gray-300">
                        <option value="">All</option>
                        @foreach (['pending', 'in_progress', 'completed'] as $st)
                            <option value="{{ $st }}" @selected($filters['status'] === $st)>
                                {{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                        @endforeach
                    </x-form.select>
                </div>
                <div class="w-[calc(50%-0.5rem)] sm:w-auto sm:flex-1 sm:min-w-[140px]">
                    <x-form.label value="From" />
                    <x-form.input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="w-full" />
                </div>
                <div class="w-[calc(50%-0.5rem)] sm:w-auto sm:flex-1 sm:min-w-[140px]">
                    <x-form.label value="To" />
                    <x-form.input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="w-full" />
                </div>
            </div>
            <input type="hidden" name="section" value="{{ $section ?? 'main' }}">
            <div class="flex items-center gap-2 flex-wrap">
                <x-button class="whitespace-nowrap">Filter</x-button>
                <x-button href="{{ route('manage.sessions.create') }}" class="whitespace-nowrap">New Assignment</x-button>
            </div>
        </form>
    </x-card>

    @php
        // Capture the current full URL (with filters/pagination) so edit/delete can redirect back here
        $currentUrl = request()->fullUrl();
    @endphp

    {{-- Section Toggle Navigation --}}
    <div class="flex items-center gap-3 overflow-x-auto pb-2 -mb-2 no-scrollbar mb-4">
        <a href="{{ route('manage.sessions.index', array_merge(request()->except(['page']), ['section' => 'main'])) }}" 
           class="px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-all duration-150 shadow-sm border
           {{ $section !== 'past' 
              ? 'bg-blue-600 border-blue-600 text-white dark:bg-blue-500 dark:border-blue-500' 
              : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700' }}">
            Today & Upcoming
        </a>
        <a href="{{ route('manage.sessions.index', array_merge(request()->except(['page']), ['section' => 'past'])) }}" 
           class="px-4 py-2 rounded-full text-xs font-semibold whitespace-nowrap transition-all duration-150 shadow-sm border
           {{ $section === 'past' 
              ? 'bg-blue-600 border-blue-600 text-white dark:bg-blue-500 dark:border-blue-500' 
              : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700' }}">
            Past Jobs
        </a>
    </div>

    {{-- Grouped List --}}
    <div class="space-y-4">
        @forelse($groupedSessions as $dateLabel => $groupSessions)
            <div x-data="{ open: true }" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-visible transition-all duration-200">
                <!-- Sticky Date Group Header -->
                <div @click="open = !open" 
                     class="sticky top-0 bg-gray-50 dark:bg-gray-800/50 backdrop-blur px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between cursor-pointer select-none hover:bg-gray-100/70 dark:hover:bg-gray-700/50 transition-colors z-10 rounded-t-xl">
                    <div class="flex items-center gap-2.5">
                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>{{ $dateLabel }}</span>
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                            {{ count($groupSessions) }} {{ Str::plural('job', count($groupSessions)) }}
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

                <!-- Group Sessions List -->
                <div x-show="open" x-collapse>
                    {{-- Mobile Card View --}}
                    <div class="md:hidden divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($groupSessions as $s)
                            <div class="p-4 bg-white dark:bg-gray-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">
                                            {{ $s->property->name }}
                                        </h3>
                                        @if($s->scheduled_time)
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span>{{ $s->scheduled_time->format('g:i A') }}</span>
                                            </p>
                                        @endif
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                            <span class="font-medium">Housekeeper:</span> {{ $s->housekeeper?->name ?? '—' }}
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        @if($s->report_token && in_array($s->status, ['in_progress', 'completed']))
                                            <a href="{{ route('reports.sessions.show', ['token' => $s->report_token]) }}" target="_blank" rel="noopener noreferrer" class="hover:opacity-80 transition-opacity inline-block" title="View Report">
                                                <x-status-badge :status="$s->status" />
                                            </a>
                                        @else
                                            <x-status-badge :status="$s->status" />
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <a href="{{ route('sessions.show', $s) }}" class="text-sm text-green-600 dark:text-green-400 font-medium">
                                            Open
                                        </a>
                                    </div>
                                    <x-action-dropdown align="right">
                                        <x-dropdown-link href="{{ route('manage.sessions.edit', $s) }}?_return_to={{ urlencode($currentUrl) }}">
                                            Edit Schedule
                                        </x-dropdown-link>
                                        <form method="post" action="{{ route('manage.sessions.destroy', $s) }}" class="block" onsubmit="return confirm('Delete assignment?')">
                                            @csrf @method('delete')
                                            <input type="hidden" name="_return_to" value="{{ $currentUrl }}">
                                            <button type="submit" class="w-full text-left px-4 py-2 text-sm leading-5 text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition">
                                                Delete
                                            </button>
                                        </form>
                                    </x-action-dropdown>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Desktop Table View --}}
                    <div class="hidden md:block">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800/40 uppercase text-xs font-semibold text-gray-500 dark:text-gray-400 border-b border-gray-150 dark:border-gray-700">
                                <tr>
                                    @if($dateLabel === 'No Date Assigned')
                                        <th class="px-4 py-2 text-left">Date</th>
                                    @endif
                                    <th class="px-4 py-2 text-left">Property</th>
                                    <th class="px-4 py-2 text-left">Housekeeper</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2 w-56 text-right pr-6">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-150 dark:divide-gray-700">
                                @foreach($groupSessions as $s)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        @if($dateLabel === 'No Date Assigned')
                                            <td class="px-4 py-3 text-gray-500">—</td>
                                        @endif
                                        <td class="px-4 py-3 truncate font-medium text-gray-900 dark:text-gray-100">
                                            <div>
                                                <div>{{ $s->property->name }}</div>
                                                @if($s->scheduled_time)
                                                    <div class="text-[10px] text-gray-400 dark:text-gray-500 font-normal flex items-center gap-1 mt-0.5">
                                                        <svg class="w-3 h-3 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                        </svg>
                                                        <span>{{ $s->scheduled_time->format('g:i A') }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $s->housekeeper?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-center">
                                            @if($s->report_token && in_array($s->status, ['in_progress', 'completed']))
                                                <a href="{{ route('reports.sessions.show', ['token' => $s->report_token]) }}" target="_blank" rel="noopener noreferrer" class="hover:opacity-80 transition-opacity inline-block" title="View Report">
                                                    <x-status-badge :status="$s->status" />
                                                </a>
                                            @else
                                                <x-status-badge :status="$s->status" />
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end whitespace-nowrap gap-2 pr-2">
                                                <a href="{{ route('sessions.show', $s) }}" class="text-sm font-semibold text-green-600 dark:text-green-400 hover:underline">
                                                    Open
                                                </a>
                                                <x-action-dropdown align="right">
                                                    <x-dropdown-link href="{{ route('manage.sessions.edit', $s) }}?_return_to={{ urlencode($currentUrl) }}">
                                                        Edit Schedule
                                                    </x-dropdown-link>
                                                    <form method="post" action="{{ route('manage.sessions.destroy', $s) }}" class="block" onsubmit="return confirm('Delete assignment?')">
                                                        @csrf @method('delete')
                                                        <input type="hidden" name="_return_to" value="{{ $currentUrl }}">
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm leading-5 text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </x-action-dropdown>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center text-gray-500 dark:text-gray-400 shadow-sm">
                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <h3 class="font-bold text-gray-900 dark:text-gray-100">No assignments found</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-xs mx-auto">
                    There are no jobs matching the selected filters in this section.
                </p>
            </div>
        @endforelse
    </div>

    {{-- Bottom Navigation Button --}}
    <div class="mt-6 flex justify-center">
        @if($section !== 'past')
            <a href="{{ route('manage.sessions.index', array_merge(request()->except(['page']), ['section' => 'past'])) }}" 
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold bg-gray-150 hover:bg-gray-200 text-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700 dark:text-gray-200 transition-all border border-gray-200 dark:border-gray-700 shadow-sm">
                <span>View Past Jobs</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path>
                </svg>
            </a>
        @else
            <a href="{{ route('manage.sessions.index', array_merge(request()->except(['page']), ['section' => 'main'])) }}" 
               class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold bg-blue-600 hover:bg-blue-700 text-white dark:bg-blue-500 dark:hover:bg-blue-600 transition-all shadow-md">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7M19 19l-7-7 7-7"></path>
                </svg>
                <span>Back to Today & Upcoming</span>
            </a>
        @endif
    </div>

    <div class="mt-4">{{ $sessions->links() }}</div>
</x-app-layout>
