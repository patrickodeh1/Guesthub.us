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
                                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 truncate">
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
                                        <x-dropdown-link href="#" @click.prevent="$dispatch('open-assignment', { id: {{ $s->id }} })">
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
                                                    <x-dropdown-link href="#" @click.prevent="$dispatch('open-assignment', { id: {{ $s->id }} })">
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
<div
    x-data="{
        jobs: @js($jobMap),
        cleaners: @js($drawerCleaners),
        hks: @js($drawerHousekeepers),
        today: @js(now()->toDateString()),
        a: { id: null, property_id: '', housekeeper_id: '', date: '', time: '10:00', status: 'pending', tasks: [], return_to: '' },
        tasks: [],
        loading: false,
        get hkOptions() {
            const ids = (this.cleaners[this.a.property_id] || []).map(Number);
            return this.hks.filter(h => ids.includes(Number(h.id)));
        },
        get formAction() {
            return this.a.id ? @js(url('/manage/sessions')) + '/' + this.a.id : @js(route('manage.sessions.store'));
        },
        openWith(d) {
            const j = d.id ? (this.jobs[d.id] || {}) : d;
            this.a = {
                id: d.id || null,
                property_id: String(j.property_id || ''),
                housekeeper_id: String(j.housekeeper_id || ''),
                date: j.date || this.today,
                time: d.id ? (j.time || '') : '10:00',
                status: j.status || 'pending',
                tasks: (j.tasks || []).map(String),
                return_to: window.location.href
            };
            this.fetchTasks();
            window.dispatchEvent(new CustomEvent('open-preview-panel', { detail: 'assignment' }));
        },
        async fetchTasks() {
            if (!this.a.property_id) { this.tasks = []; return; }
            this.loading = true;
            try {
                const res = await fetch('/api/properties/' + this.a.property_id + '/sporadic-tasks');
                this.tasks = res.ok ? await res.json() : [];
            } catch (e) { this.tasks = []; }
            this.loading = false;
        }
    }"
    x-on:open-assignment.window="openWith($event.detail)"
    x-init="@if(request()->boolean('new')) setTimeout(() => openWith({ property_id: @js(request('new_property')), date: @js(request('new_date')) }), 80) @endif">
    <x-preview-panel name="assignment" title="Cleaning assignment" subtitle="Assign a cleaner to a property on a date" initialWidth="28rem" minWidth="20rem">
        <form id="assignment-form" method="post" x-bind:action="formAction" class="grid gap-4 text-sm">
            @csrf
            <input type="hidden" name="_method" value="PUT" x-bind:disabled="!a.id">
            <input type="hidden" name="_return_to" x-bind:value="a.return_to">

            <div>
                <label class="block font-medium text-gray-700 dark:text-gray-200">Property</label>
                <select name="property_id" required x-model="a.property_id" @change="a.housekeeper_id = ''; a.tasks = []; fetchTasks()" class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    <option value="">Select property…</option>
                    @foreach($drawerProps as $dp)
                        <option value="{{ $dp['id'] }}">{{ $dp['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block font-medium text-gray-700 dark:text-gray-200">Housekeeper</label>
                <select name="housekeeper_id" required class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    <option value="">Select housekeeper…</option>
                    <template x-for="h in hkOptions" :key="h.id">
                        <option :value="h.id" x-text="h.name" :selected="String(h.id) === String(a.housekeeper_id)"></option>
                    </template>
                </select>
                <p x-show="a.property_id && hkOptions.length === 0" x-cloak class="mt-1 text-xs text-amber-600">No housekeepers are assigned to this property. Assign one from the user's page.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block font-medium text-gray-700 dark:text-gray-200">Date</label>
                    <input type="date" name="scheduled_date" required x-model="a.date" class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                </div>
                <div>
                    <label class="block font-medium text-gray-700 dark:text-gray-200">Time</label>
                    <input type="time" name="scheduled_time" step="1800" x-model="a.time" class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                </div>
            </div>

            <div x-show="a.id" x-cloak>
                <label class="block font-medium text-gray-700 dark:text-gray-200">Status</label>
                <select name="status" x-model="a.status" x-bind:disabled="!a.id" class="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800">
                    <option value="pending">Pending</option>
                    <option value="in_progress">In progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>

            <div x-show="a.property_id" x-cloak>
                <label class="block font-medium text-gray-700 dark:text-gray-200">Occasional tasks</label>
                <p class="text-xs text-gray-500">Extra tasks for this cleaning only.</p>
                <p x-show="loading" class="mt-2 text-xs text-gray-500">Loading tasks…</p>
                <p x-show="!loading && tasks.length === 0" class="mt-2 text-xs italic text-gray-500">No occasional tasks for this property.</p>
                <div x-show="!loading && tasks.length > 0" class="mt-2 divide-y divide-gray-200 rounded-lg border border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                    <template x-for="t in tasks" :key="t.id">
                        <label class="flex cursor-pointer items-center gap-3 px-3 py-2">
                            <input type="checkbox" name="sporadic_tasks[]" :value="t.id" x-model="a.tasks" class="h-4 w-4 rounded border-gray-300">
                            <span class="flex-1" x-text="t.name"></span>
                            <span class="text-xs text-gray-500" x-text="t.last_done ? 'Last done ' + t.last_done : 'Never done'"></span>
                        </label>
                    </template>
                </div>
            </div>
        </form>

        <form id="assignment-delete-form" method="post" x-bind:action="formAction" class="hidden">
            @csrf
            <input type="hidden" name="_method" value="DELETE">
            <input type="hidden" name="_return_to" x-bind:value="a.return_to">
        </form>

        <x-slot name="footer">
            <div class="flex items-center gap-2">
                <button type="submit" form="assignment-form" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" x-text="a.id ? 'Update' : 'Create'"></button>
                <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700" @click="$dispatch('close-preview-panel', 'assignment')">Cancel</button>
                <button type="submit" form="assignment-delete-form" x-show="a.id" x-cloak class="ml-auto rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700" onclick="return confirm('Delete assignment?')">Delete</button>
            </div>
        </x-slot>
    </x-preview-panel>
</div>

</x-app-layout>
