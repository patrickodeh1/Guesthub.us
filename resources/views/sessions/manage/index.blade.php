<x-app-layout>
    @php
        $dateFormat = \App\Models\Setting::get('date_format', 'M d, Y');
    @endphp
    <x-slot name="header">
        <h2 class="text-lg sm:text-xl font-semibold">Cleaning Schedule</h2>
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
                    <x-form.label value="Cleaner" />
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
                <x-button type="button" @click="$dispatch('open-assignment', {})" class="whitespace-nowrap">New Assignment</x-button>
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

    @if(($needsCleaner ?? collect())->isNotEmpty())
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-700/50 dark:bg-amber-900/20">
            <h3 class="text-sm font-bold text-amber-800 dark:text-amber-200">Needs a cleaner ({{ $needsCleaner->count() }})</h3>
            <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-300">Checkouts in the next 30 days with no cleaning job on that date.</p>
            <div class="mt-3 divide-y divide-amber-200 dark:divide-amber-700/50">
                @foreach($needsCleaner as $n)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div class="min-w-0 text-sm">
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $n['property_name'] }}</span>
                            <span class="text-gray-600 dark:text-gray-300"> &middot; checkout {{ \Carbon\Carbon::parse($n['date'])->format($dateFormat) }}</span>
                            <a href="{{ route('admin.guests.show', $n['booking_id']) }}" class="ml-1 text-xs text-indigo-600 hover:underline">{{ $n['guest_name'] }}</a>
                        </div>
                        <x-button type="button" @click="$dispatch('open-assignment', { property_id: {{ $n['property_id'] }}, date: '{{ $n['date'] }}' })" class="whitespace-nowrap">Assign cleaner</x-button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

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
                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 min-w-0 break-words">
                                            {{ $s->property->name }}
                                        </h3>
                                        @if(isset($bookingByJob[$s->id]))
                                            <a href="{{ route('admin.guests.show', $bookingByJob[$s->id]['id']) }}" class="mt-0.5 block text-xs font-normal text-indigo-600 hover:underline">Guest: {{ $bookingByJob[$s->id]['guest'] }}</a>
                                        @endif
                                        @if($s->scheduled_time)
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span>{{ $s->scheduled_time->format('g:i A') }}</span>
                                            </p>
                                        @endif
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                            <span class="font-medium">Cleaner:</span> {{ $s->housekeeper?->name ?? '—' }}
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0">
                                        @if($s->report_token && in_array($s->status, ['in_progress', 'completed']))
                                            <a href="{{ route('reports.sessions.show', ['token' => $s->report_token]) }}" target="_blank" rel="noopener noreferrer" class="hover:opacity-80 transition-opacity inline-block" title="View Report">
                                                @include('sessions._assignment_badge', ['s' => $s])
                                            </a>
                                        @else
                                            @include('sessions._assignment_badge', ['s' => $s])
                                        @endif
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
                                    <div></div>
                                    <x-action-dropdown align="right">
                                        <x-dropdown-link :href="route('sessions.show', $s)">
    Start cleaning
</x-dropdown-link>
<x-dropdown-link href="#" @click.prevent="$dispatch('open-assignment', { id: {{ $s->id }} })">
    Edit cleaning
</x-dropdown-link>
@if (auth()->user()?->hasAnyRole(['admin', 'owner', 'company']) && $s->status === 'pending' && ! $s->gps_override_enabled)
<button type="button" class="w-full text-left px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition"
    @click="$dispatch('gps-override-open', { action: '{{ route('sessions.gps-override', $s) }}', label: @js($s->property->name ?? '') }); $dispatch('open-modal', 'manage-gps-override-modal')">
    Grant GPS override
</button>
@endif
                                        {{-- gh-confirm-manually --}}
                                        @if (auth()->user()?->hasAnyRole(['admin', 'owner', 'company']) && $s->housekeeper_id && $s->status === 'pending' && $s->assignment_status === 'pending_confirmation')
                                        <button type="button" class="w-full text-left px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition"
                                            @click="fetch('{{ route('admin.cleaning-jobs.confirm-manually') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ session_id: {{ $s->id }} }) }).then(r => r.ok ? window.location.reload() : r.json().then(j => alert(j.message || 'Could not confirm this job.'))).catch(() => alert('Network error. Please try again.'))">
                                            Confirm manually
                                        </button>
                                        @endif
                                        <form method="post" action="{{ route('manage.sessions.destroy', $s) }}" class="block" onsubmit="return confirm('Delete this cleaning?')">
                                            @csrf @method('delete')
                                            <input type="hidden" name="_return_to" value="{{ $currentUrl }}">
                                            <button type="submit" class="w-full text-left px-4 py-2 text-sm leading-5 text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition">
                                                Delete cleaning
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
                                    <th class="px-4 py-2 text-left">Cleaner</th>
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
                                        <td class="px-4 py-3 min-w-0 break-words font-medium text-gray-900 dark:text-gray-100">
                                            <div>
                                                <div>{{ $s->property->name }}</div>
                                        @if(isset($bookingByJob[$s->id]))
                                            <a href="{{ route('admin.guests.show', $bookingByJob[$s->id]['id']) }}" class="mt-0.5 block text-xs font-normal text-indigo-600 hover:underline">Guest: {{ $bookingByJob[$s->id]['guest'] }}</a>
                                        @endif
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
                                                    @include('sessions._assignment_badge', ['s' => $s])
                                                </a>
                                            @else
                                                @include('sessions._assignment_badge', ['s' => $s])
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex items-center justify-end whitespace-nowrap gap-2 pr-2">
                                                <x-action-dropdown align="right">
                                                    <x-dropdown-link :href="route('sessions.show', $s)">
    Start cleaning
</x-dropdown-link>
<x-dropdown-link href="#" @click.prevent="$dispatch('open-assignment', { id: {{ $s->id }} })">
    Edit cleaning
</x-dropdown-link>
@if (auth()->user()?->hasAnyRole(['admin', 'owner', 'company']) && $s->status === 'pending' && ! $s->gps_override_enabled)
<button type="button" class="w-full text-left px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition"
    @click="$dispatch('gps-override-open', { action: '{{ route('sessions.gps-override', $s) }}', label: @js($s->property->name ?? '') }); $dispatch('open-modal', 'manage-gps-override-modal')">
    Grant GPS override
</button>
@endif
                                                    {{-- gh-confirm-manually --}}
                                                    @if (auth()->user()?->hasAnyRole(['admin', 'owner', 'company']) && $s->housekeeper_id && $s->status === 'pending' && $s->assignment_status === 'pending_confirmation')
                                                    <button type="button" class="w-full text-left px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition"
                                                        @click="fetch('{{ route('admin.cleaning-jobs.confirm-manually') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ session_id: {{ $s->id }} }) }).then(r => r.ok ? window.location.reload() : r.json().then(j => alert(j.message || 'Could not confirm this job.'))).catch(() => alert('Network error. Please try again.'))">
                                                        Confirm manually
                                                    </button>
                                                    @endif
                                                    <form method="post" action="{{ route('manage.sessions.destroy', $s) }}" class="block" onsubmit="return confirm('Delete this cleaning?')">
                                                        @csrf @method('delete')
                                                        <input type="hidden" name="_return_to" value="{{ $currentUrl }}">
                                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm leading-5 text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none transition">
                                                            Delete cleaning
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
@php
    $jobMap = collect($sessions->items())->mapWithKeys(function ($s) {
        $t = $s->scheduled_time;
        $time = $t ? (is_string($t) ? substr($t, 0, 5) : $t->format('H:i')) : '';
        return [$s->id => [
            'property_id' => $s->property_id,
            'housekeeper_id' => $s->housekeeper_id,
            'date' => $s->scheduled_date ? \Carbon\Carbon::parse($s->scheduled_date)->toDateString() : '',
            'time' => $time,
            'status' => $s->status,
            'tasks' => $s->sporadic_tasks ?? [],
        ]];
    });
@endphp
<x-schedule-drawer />

@if (auth()->user()?->hasAnyRole(['admin', 'owner', 'company']))
    <x-modal name="manage-gps-override-modal" :show="false" maxWidth="md">
        <form method="post" x-data="{ action: '', label: '' }"
              @gps-override-open.window="action = $event.detail.action; label = $event.detail.label"
              :action="action" class="p-6">
            @csrf
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Grant GPS Override</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Authorize the cleaner to start this cleaning<span x-show="label"> at <span x-text="label"></span></span> without passing GPS verification.
            </p>
            <div class="mt-4">
                <x-form.label for="manage-gps-reason" value="Reason for Override" />
                <x-form.input id="manage-gps-reason" name="reason" type="text" maxlength="255" class="mt-1 block w-full" required placeholder="e.g., Cleaner's phone GPS broken" />
            </div>
            <div class="mt-6 flex justify-end">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close')">Cancel</x-button>
                <x-button type="submit" class="ml-3">Grant Override</x-button>
            </div>
        </form>
    </x-modal>
@endif

</x-app-layout>
