<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl">
                {{ $acting === 'housekeeper' ? 'My Schedule' : 'Cleaning Sessions Calendar' }}
            </h2>
            <div class="flex flex-wrap items-center gap-2">
                {{-- Role scope indicator (admin can act as owner via ?as=owner) --}}
                <span class="text-xs px-2 py-1 rounded border bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                    Scope: {{ ucfirst($acting) }}
                </span>
                <x-button variant="secondary"
                    :href="$prevUrl"
                    class="!py-1">← Prev</x-button>
                <x-button variant="secondary" :href="$todayUrl" class="!py-1">Today</x-button>
                <x-button variant="secondary"
                    :href="$nextUrl"
                    class="!py-1">Next →</x-button>
            </div>
        </div>
    </x-slot>

    @php
        $pickerList = collect($pickerProperties ?? [])->map(fn ($p) => [
            'id' => $p['id'],
            'name' => $p['name'],
            'has_owner' => $p['has_owner'],
            'cleaners' => $cleanerOptions[$p['id']] ?? [],
        ])->values()->all();
    @endphp
    <div x-data="calendarAssign(@js($pickerList))">
    @php
        $filterLabel = fn ($s) => ucfirst(str_replace('_', ' ', (string) $s));
        $filtersOn = collect(request()->only(['property', 'guest', 'cleaner', 'cleaning_status', 'reservation_status', 'coverage']))->filter()->isNotEmpty();
        $selCls = 'rounded border-gray-300 py-1 text-xs dark:border-gray-600 dark:bg-gray-900';
    @endphp
    <form method="GET" action="{{ route('calendar.index') }}" class="mb-4 flex flex-wrap items-end gap-2 rounded border border-gray-200 bg-white p-3 text-xs dark:border-gray-700 dark:bg-gray-800">
        <input type="hidden" name="month" value="{{ $monthStart->format('Y-m') }}">
        @if ($viewMode !== 'month')<input type="hidden" name="view" value="{{ $viewMode }}">@endif
        @if (request('as'))<input type="hidden" name="as" value="{{ request('as') }}">@endif
        @if ($selectedDay)<input type="hidden" name="day" value="{{ $selectedDay }}">@endif
        @if ($acting !== 'housekeeper')
            <label class="flex flex-col gap-0.5"><span class="text-gray-500">Property</span>
                <select name="property" class="{{ $selCls }}" onchange="this.form.submit()">
                    <option value="">All properties</option>
                    @foreach (collect($pickerProperties ?? [])->sortBy('name') as $p)
                        <option value="{{ $p['id'] }}" @selected((string) request('property') === (string) $p['id'])>{{ $p['name'] }}</option>
                    @endforeach
                </select></label>
            <label class="flex flex-col gap-0.5"><span class="text-gray-500">Guest</span>
                <input type="text" name="guest" value="{{ request('guest') }}" placeholder="Name" class="{{ $selCls }} w-32"></label>
            <label class="flex flex-col gap-0.5"><span class="text-gray-500">Reservation</span>
                <select name="reservation_status" class="{{ $selCls }}" onchange="this.form.submit()">
                    <option value="">Any status</option>
                    @foreach ($reservationStatusOptions as $s)
                        <option value="{{ $s }}" @selected(request('reservation_status') === $s)>{{ $filterLabel($s) }}</option>
                    @endforeach
                </select></label>
            <label class="flex flex-col gap-0.5"><span class="text-gray-500">Cleaner</span>
                <select name="cleaner" class="{{ $selCls }}" onchange="this.form.submit()">
                    <option value="">Any cleaner</option>
                    @foreach ($cleanerFilterOptions as $id => $name)
                        <option value="{{ $id }}" @selected((string) request('cleaner') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select></label>
        @endif
        <label class="flex flex-col gap-0.5"><span class="text-gray-500">Cleaning</span>
            <select name="cleaning_status" class="{{ $selCls }}" onchange="this.form.submit()">
                <option value="">Any status</option>
                @foreach ($cleaningStatusOptions as $s)
                    <option value="{{ $s }}" @selected(request('cleaning_status') === $s)>{{ $filterLabel($s) }}</option>
                @endforeach
            </select></label>
        @if ($acting !== 'housekeeper')
            <label class="flex flex-col gap-0.5"><span class="text-gray-500">Coverage</span>
                <select name="coverage" class="{{ $selCls }}" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="covered" @selected(request('coverage') === 'covered')>Covered</option>
                    <option value="needs" @selected(request('coverage') === 'needs')>Needs cleaner</option>
                </select></label>
        @endif
        <button type="submit" class="rounded bg-indigo-600 px-3 py-1 font-medium text-white hover:bg-indigo-700">Apply</button>
        @hasanyrole('admin|owner|company')
            <button type="button" class="rounded border border-indigo-600 px-3 py-1 font-medium text-indigo-700 hover:bg-indigo-50" @click="$dispatch('open-add-guest')">+ Add guest</button>
        @endhasanyrole
        @if ($filtersOn)
            <a href="{{ route('calendar.index', array_filter(['month' => $monthStart->format('Y-m'), 'as' => request('as')])) }}" class="py-1 text-gray-500 underline">Clear filters</a>
        @endif
    </form>
    <div id="calendar-root" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Calendar grid --}}
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <div class="font-medium">
                        {{ $viewMode === 'month' ? $monthStart->format('F Y') : $rangeLabel }}
                        <span class="ml-3 inline-flex overflow-hidden rounded border border-gray-300 align-middle text-xs dark:border-gray-600">
                            @foreach ($viewLinks as $k => $vl)
                                <a href="{{ $vl['url'] }}" class="px-2 py-0.5 {{ $viewMode === $k ? 'bg-indigo-600 font-medium text-white' : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300' }}">{{ $vl['label'] }}</a>
                            @endforeach
                        </span>
                    </div>
                    {{-- Quick legend --}}
                    <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-3">
                        <span class="inline-flex items-center gap-1"><span
                                class="h-2 w-2 rounded-full bg-indigo-600 inline-block"></span> Assigned</span>
                                                @if($acting !== 'housekeeper')
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500 inline-block"></span> Check-in</span>
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-sky-500 inline-block"></span> Check-out</span>
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-600 inline-block"></span> Conflict</span>
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-400 inline-block"></span> Back-to-back</span>
                            <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-slate-400 inline-block"></span> Blocked</span>
                        @endif
                        @if($acting !== 'housekeeper')
                            <span class="inline-flex items-center gap-1"><span
                                    class="h-2 w-2 rounded-full bg-orange-500 inline-block"></span> Needs cleaner</span>
                        @endif
                    </div>
                </div>

                <div class="p-2">
                    @if ($viewMode === 'month')
                    {{-- Weekday headers - Start with Sunday --}}
                    <div class="grid grid-cols-7 text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                        @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $w)
                            <div class="px-2 py-2 text-center">{{ $w }}</div>
                        @endforeach
                    </div>

                    {{-- Days --}}
                    @php
                        $jobSide = request()->filled('cleaner') || request()->filled('cleaning_status') || in_array(request('coverage'), ['covered', 'needs'], true);
                        $bookSide = request()->filled('guest') || request()->filled('reservation_status');
                        $anyFilter = $jobSide || $bookSide || request()->filled('property');
                        $dayMatches = function ($day) use ($jobSide, $bookSide) {
                            $m = $day['marks'] ?? null;
                            $pending = ($day['unscheduledCount'] ?? 0) > 0;
                            $jobHit = ($day['sessionCount'] ?? 0) > 0 || $pending;
                            $bookHit = (!empty($m) && (($m['in'] ?? 0) + ($m['out'] ?? 0) + ($m['stay'] ?? 0)) > 0) || $pending;
                            if ($jobSide && $bookSide) return $jobHit && $bookHit;
                            if ($jobSide) return $jobHit;
                            if ($bookSide) return $bookHit;
                            return $jobHit || $bookHit;
                        };
                        $matchCount = $anyFilter ? collect($days)->filter(fn ($d) => $d['inMonth'] && $dayMatches($d))->count() : 0;
                    @endphp
                    @if ($anyFilter)
                        <div class="mb-2 px-1 text-xs text-gray-600 dark:text-gray-300">{{ $matchCount }} {{ $matchCount === 1 ? 'day matches' : 'days match' }} your filters (highlighted)</div>
                    @endif
                    <div class="grid grid-cols-7 text-sm text-center">
                        @foreach ($days as $day)
                            @php
                                $dDate = $day['date'];
                                $isSel = $selectedDay === $dDate;
                                $hasSession = $day['sessionCount'] > 0;
                                $hasUnscheduled = ($day['unscheduledCount'] ?? 0) > 0;
                                $isMatch = $anyFilter && $dayMatches($day);
                            @endphp
                            <a href="{{ route('calendar.index', ['month' => \Carbon\Carbon::parse($dDate)->format('Y-m'), 'day' => $dDate] + request()->except('page')) }}"
                                class="h-24 border -m-[0.5px] p-1 relative flex flex-col justify-between
                  {{ $isMatch ? 'bg-indigo-100 dark:bg-indigo-900/40' : ($day['inMonth'] ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-900/40') }}
                  border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition
                  {{ $isSel ? 'ring-2 ring-indigo-600 z-10' : '' }}
                  {{ ($anyFilter && !$isMatch && !$isSel) ? 'opacity-40' : '' }}
                ">
                                <div class="flex items-start justify-between w-full">
                                    @if ($day['isToday'])
                                        <span class="flex items-center justify-center w-6 h-6 mt-0.5 ml-0.5 rounded-full bg-red-600 text-white text-xs font-bold shadow-sm">
                                            {{ \Carbon\Carbon::parse($dDate)->format('j') }}
                                        </span>
                                    @else
                                        <span class="text-xs ml-1 mt-1 font-medium {{ $day['inMonth'] ? 'text-gray-900 dark:text-gray-100' : 'text-gray-400' }}">
                                            {{ \Carbon\Carbon::parse($dDate)->format('j') }}
                                        </span>
                                    @endif
                                </div>
                                
                                <div class="flex flex-col gap-1 items-center w-full mb-1">
                                    @if ($hasSession)
                                        <div class="w-full px-1">
                                            <div class="bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 text-[10px] rounded px-1 py-0.5 truncate text-center">
                                                {{ $day['sessionCount'] }} task{{ $day['sessionCount'] > 1 ? 's' : '' }}
                                            </div>
                                        </div>
                                    @endif
                                                                        @if ($acting !== 'housekeeper' && !empty($day['marks']))
                                        <div class="flex flex-wrap justify-center gap-1 px-1">
                                            @if ($day['marks']['in'] > 0)<span class="rounded bg-emerald-100 px-1 text-[10px] text-emerald-800">&darr; {{ $day['marks']['in'] }} in</span>@endif
                                            @if ($day['marks']['out'] > 0)<span class="rounded bg-sky-100 px-1 text-[10px] text-sky-800">&uarr; {{ $day['marks']['out'] }} out</span>@endif
                                            @if ($day['marks']['stay'] > 0)<span class="rounded bg-slate-100 px-1 text-[10px] text-slate-600">{{ $day['marks']['stay'] }} staying</span>@endif
                                            @if ($day['marks']['conflict'])<span class="rounded bg-red-600 px-1 text-[10px] font-bold text-white">Conflict</span>@endif
                                            @if (!empty($day['marks']['b2b']))<span class="rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-800">Back-to-back</span>@endif
                                        </div>
                                    @endif
                                    @if ($acting !== 'housekeeper' && ($day['blocked'] ?? 0) > 0)
                                        <div class="w-full px-1">
                                            <div class="bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-[10px] rounded px-1 py-0.5 truncate text-center">
                                                {{ $day['blocked'] }} blocked
                                            </div>
                                        </div>
                                    @endif
                                    @if ($hasUnscheduled && $acting !== 'housekeeper')
                                        <div class="w-full px-1">
                                            <div class="bg-orange-100 dark:bg-orange-900/50 text-orange-700 dark:text-orange-300 text-[10px] rounded px-1 py-0.5 truncate text-center">
                                                {{ $day['unscheduledCount'] }} checkout{{ $day['unscheduledCount'] > 1 ? 's' : '' }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                    @endif
                    @if ($viewMode !== 'month')
                        <div class="grid grid-cols-1 gap-3 {{ $viewMode === 'week' ? 'md:grid-cols-2' : '' }}">
                            @foreach ($agenda as $ag)
                                <div class="rounded border p-3 text-left {{ $ag['isToday'] ? 'border-red-300' : 'border-gray-200 dark:border-gray-700' }} {{ $selectedDay === $ag['date'] ? 'ring-2 ring-indigo-600' : '' }}">
                                    <a href="{{ route('calendar.index', ['month' => substr($ag['date'], 0, 7), 'day' => $ag['date']] + request()->except('page')) }}"
                                        class="mb-2 flex items-center justify-between text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        <span>{{ $ag['label'] }}</span>
                                        @if ($ag['isToday'])<span class="rounded bg-red-600 px-1.5 text-[10px] font-bold text-white">Today</span>@endif
                                    </a>

                                    @if (empty($ag['sessions']) && empty($ag['pending']) && empty($ag['bookings']) && empty($ag['blocked']))
                                        <p class="text-xs text-gray-400">Nothing scheduled.</p>
                                    @endif

                                    <div class="space-y-2">
                                        @foreach ($ag['pending'] as $p)
                                            <div class="rounded border border-orange-200 bg-orange-50 p-2 text-xs dark:border-orange-800 dark:bg-orange-900/10">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $p['property_name'] }}</span>
                                                    <span class="rounded bg-orange-100 px-1 text-[10px] font-bold uppercase text-orange-800">Checkout, needs cleaner</span>
                                                </div>
                                                <div class="mt-0.5 text-orange-800 dark:text-orange-300">Guest: @if (!empty($p['booking_id']))<a href="{{ route('admin.guests.show', $p['booking_id']) }}" class="underline">{{ $p['guest_name'] }}</a>@else{{ $p['guest_name'] }}@endif</div>
                                                @if ($acting !== 'housekeeper')
                                                    <button type="button" class="mt-1.5 w-full rounded bg-indigo-600 py-1 text-center font-medium text-white hover:bg-indigo-700"
                                                        @click="openAssign(@js(['property_id' => $p['property_id'], 'property_name' => $p['property_name'], 'date' => $p['date'], 'session_id' => null, 'housekeeper_id' => null, 'scheduled_time' => null, 'cleaners' => $cleanerOptions[$p['property_id']] ?? []]))">Assign cleaner</button>
                                                @endif
                                            </div>
                                        @endforeach

                                        @foreach ($ag['sessions'] as $s)
                                            <div class="rounded border border-indigo-200 bg-indigo-50 p-2 text-xs dark:border-indigo-800 dark:bg-indigo-900/20">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $s['property'] }}</span>
                                                    <span class="text-indigo-700 dark:text-indigo-300">{{ $s['time'] ?? 'No time' }}</span>
                                                </div>
                                                <div class="mt-0.5 flex items-center justify-between gap-2">
                                                    @if ($s['cleaner'])<span class="text-gray-700 dark:text-gray-300">{{ $s['cleaner'] }}</span>@else<span class="font-semibold text-orange-700">Needs cleaner</span>@endif
                                                    @if ($s['status'])<span class="text-gray-500">{{ ucfirst(str_replace('_', ' ', (string) $s['status'])) }}</span>@endif
                                                </div>
                                            </div>
                                        @endforeach

                                        @foreach ($ag['bookings'] as $b)
                                            <div class="rounded border border-gray-200 p-2 text-xs dark:border-gray-700">
                                                <div class="flex items-center justify-between gap-2">
                                                    <a href="{{ route('admin.guests.show', $b['id']) }}" class="font-medium text-gray-900 underline dark:text-gray-100">{{ $b['guest'] }}</a>
                                                    @if ($b['kind'] === 'in')<span class="rounded bg-emerald-100 px-1 text-[10px] text-emerald-800">Check-in{{ $b['time'] ? ' ' . $b['time'] : '' }}</span>
                                                    @elseif ($b['kind'] === 'out')<span class="rounded bg-sky-100 px-1 text-[10px] text-sky-800">Check-out{{ $b['time'] ? ' ' . $b['time'] : '' }}</span>
                                                    @else<span class="rounded bg-slate-100 px-1 text-[10px] text-slate-600">Staying</span>@endif
                                                </div>
                                                <div class="mt-0.5 flex flex-wrap items-center gap-1 text-gray-500">
                                                    <span>{{ $b['property'] }}</span>
                                                    @hasanyrole('admin|owner|company')<button type="button" class="rounded border border-gray-300 px-1 text-[10px] text-gray-700 hover:bg-gray-100" @click="$dispatch('open-guest', @js(['url' => route('admin.guests.show', $b['id']).'?embed=1', 'title' => $b['guest']]))">Edit</button>@endhasanyrole
                                                    @if ($b['conflict'])<span title="{{ collect($b['conflict_with'] ?? [])->map(fn ($c) => 'Clashes with '.$c['guest'].' ('.$c['stay'].'), shared '.$c['shared'])->implode('; ') }}" class="rounded bg-red-600 px-1 text-[10px] font-bold text-white">Conflict</span>@endif
                                                    @if ($b['turnover'])<span class="rounded bg-amber-100 px-1 text-[10px] font-semibold text-amber-800">Back-to-back</span>@endif
                                                </div>
                                            </div>
                                        @endforeach

                                        @if ($ag['blocked'] > 0 && $acting !== 'housekeeper')
                                            <div class="rounded bg-slate-200 px-2 py-1 text-xs text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $ag['blocked'] }} blocked</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Day details --}}
        <div>
            <div class="bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700 h-full flex flex-col">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                    <div class="font-medium text-lg">
                        {{ $selectedDay ? \Carbon\Carbon::parse($selectedDay)->toFormattedDateString() : 'Select a date' }}
                    </div>
                </div>
                <div class="p-4 flex-1 overflow-y-auto max-h-[calc(100vh-200px)]">
                    @if (!$selectedDay)
                        <div class="flex flex-col items-center justify-center h-full text-gray-500 text-center py-12">
                            <svg class="w-12 h-12 mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <p class="text-sm">Select a date on the calendar<br>to view details.</p>
                        </div>
                    @else
                    @if ($daySessions->isEmpty() && $dayUnscheduled->isEmpty() && ($dayBookings ?? collect())->isEmpty())
                        <div class="text-center py-8 text-gray-500">
                            <p class="text-sm">No assignments or checkouts found.</p>
                        </div>
                    @else
                        {{-- Unscheduled Section --}}
                        @if($dayUnscheduled->isNotEmpty())
                            <div class="mb-6">
                                <h4 class="text-xs font-semibold text-orange-600 dark:text-orange-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                                    Pending Checkouts
                                </h4>
                                <div class="space-y-3">
                                    @foreach($dayUnscheduled as $u)
                                        <div class="bg-orange-50 dark:bg-orange-900/10 border border-orange-200 dark:border-orange-800 rounded-lg p-3">
                                             <div class="flex justify-between items-start mb-2">
                                                 <div>
                                                     <div class="font-medium text-gray-900 dark:text-gray-100 text-sm">{{ $u['property_name'] }}</div>
                                                     <div class="text-xs text-orange-800 dark:text-orange-300 mt-0.5 flex items-center justify-between">
                                                         <span>Guest: @if(!empty($u['booking_id']))<a href="{{ route('admin.guests.show', $u['booking_id']) }}" class="underline">{{ $u['guest_name'] }}</a>@else{{ $u['guest_name'] }}@endif</span>
                                                         @if(!empty($u['source']))
                                                             <span class="px-1.5 py-0.5 rounded bg-orange-100 dark:bg-orange-800/30 text-[9px] font-bold uppercase">{{ $u['source'] }}</span>
                                                         @endif
                                                     </div>
                                                 </div>
                                             </div>
                                             <button type="button" class="mb-2 block w-full rounded bg-indigo-600 py-1.5 text-center text-xs font-medium text-white hover:bg-indigo-700"
                                                @click="openAssign(@js([
                                                    'property_id' => $u['property_id'],
                                                    'property_name' => $u['property_name'],
                                                    'date' => $u['date'],
                                                    'session_id' => null,
                                                    'housekeeper_id' => null,
                                                    'scheduled_time' => null,
                                                    'cleaners' => $cleanerOptions[$u['property_id']] ?? [],
                                                ]))">Assign cleaner</button>
                                             <a href="{{ route('manage.sessions.create', [
                                                    'property_id' => $u['property_id'],
                                                    'date' => $u['date'],
                                                ]) }}" 
                                                class="block w-full text-center py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-medium rounded transition">
                                                 Schedule Cleaning
                                             </a>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif

                                        

                    @if ($acting !== 'housekeeper' && ($dayBookings ?? collect())->isNotEmpty())
                        <div class="mb-6">
                            <h4 class="text-xs font-semibold text-emerald-700 uppercase tracking-wider mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Guests
                            </h4>
                            <ul class="space-y-2">
                                @foreach ($dayBookings as $item)
                                    <li class="rounded-lg border p-3 text-sm {{ $item['conflict'] ? 'border-red-300 bg-red-50' : 'border-gray-100 bg-white' }} dark:border-gray-700 dark:bg-gray-800">
                                        <div class="flex items-center justify-between gap-2">
                                            <a href="{{ route('admin.guests.show', $item['booking']) }}" class="truncate font-medium text-indigo-700 hover:underline">{{ $item['booking']->guest_name ?: 'Guest' }}</a>
                                            <span class="shrink-0 text-[10px] font-bold uppercase text-gray-500">{{ $item['role'] }}</span>
                                        </div>
                                        <div class="mt-0.5 truncate text-xs text-gray-500">{{ $item['booking']->property?->name }}</div>
                                        @hasanyrole('admin|owner|company')<button type="button" class="mt-1 rounded border border-gray-300 px-2 py-0.5 text-xs text-gray-700 hover:bg-gray-100" @click="$dispatch('open-guest', @js(['url' => route('admin.guests.show', $item['booking']).'?embed=1', 'title' => $item['booking']->guest_name ?: 'Guest']))">Edit</button>@endhasanyrole
                                        <div class="text-xs text-gray-500">{{ $item['booking']->check_in_date->format('M j') }}@if ($item['in_time']) &middot; {{ $item['in_time'] }}@endif &rarr; {{ $item['booking']->check_out_date->format('M j') }}@if ($item['out_time']) &middot; {{ $item['out_time'] }}@endif</div>
                                        @if ($item['conflict'])<div class="mt-1 text-xs font-bold text-red-700">Overlaps another booking on this property</div>@foreach (($item['conflict_with'] ?? []) as $cw)<div class="text-xs text-red-700">Clashes with {{ $cw['guest'] }} ({{ $cw['stay'] }}). Shared: {{ $cw['shared'] }}</div>@endforeach @endif
                                        @if ($item['turnover'] && !$item['conflict'])<div class="mt-1 text-xs font-semibold text-amber-700">Back-to-back: same-day turnover on this property</div>@endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @hasanyrole('admin|owner|company')
                         <div class="mt-4 {{ ($daySessions->isNotEmpty() || $dayUnscheduled->isNotEmpty() || ($dayBookings ?? collect())->isNotEmpty()) ? 'pt-4 border-t border-gray-100 dark:border-gray-800' : '' }}">
                             <button type="button" class="btn-secondary w-full !text-xs" @click="openCreate(@js($selectedDay))">+ Schedule New Session</button>
                         </div>
                    @endhasanyrole

                            {{-- Assigned Section --}}
                            @if ($daySessions->isNotEmpty())
                                <div>
                                    <h4 class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-3 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                        Scheduled Sessions
                                    </h4>
                                    <ul class="space-y-3">
                                        @foreach ($daySessions as $s)
                                            <li class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm rounded-lg p-3 hover:shadow-md transition group">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-medium text-gray-900 dark:text-gray-100 text-sm truncate">{{ $s->property->name }}</div>
                                                        <div class="flex flex-col gap-0.5 mt-1">
                                                            @if ($s->scheduled_time)
                                                                <div class="text-xs font-medium text-indigo-600 dark:text-indigo-400 flex items-center gap-1">
                                                                     <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                                     {{ \Carbon\Carbon::parse($s->scheduled_time)->format('g:i A') }}
                                                                </div>
                                                            @endif
                                                            @if ($acting !== 'housekeeper')
                                                                <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                                    To: {{ $s->housekeeper?->name ?? 'Unassigned' }}
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="flex flex-col items-end gap-2 ml-2">
                                                        <x-status-badge :status="$s->status" class="!text-[10px] !px-1.5 !py-0.5" />
                                                        @if ($acting !== 'housekeeper')
                                                            <select class="input !py-0.5 !text-[11px] w-auto" aria-label="Change cleaning status"
                                                                @change="changeStatus({{ $s->id }}, $event.target, '{{ $s->status }}')">
                                                                @foreach (['pending' => 'Pending', 'in_progress' => 'In progress', 'completed' => 'Completed'] as $value => $label)
                                                                    <option value="{{ $value }}" @selected($s->status === $value)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        @endif
                                                        @if ($acting !== 'housekeeper')
                                                            <button type="button" class="text-xs font-medium text-gray-600 underline hover:text-gray-900"
                                                                @click="openAssign(@js([
                                                                    'property_id' => $s->property_id,
                                                                    'property_name' => $s->property?->name,
                                                                    'date' => substr((string) $s->scheduled_date, 0, 10),
                                                                    'session_id' => $s->id,
                                                                    'housekeeper_id' => $s->housekeeper_id,
                                                                    'scheduled_time' => $s->scheduled_time?->format('H:i'),
                                                                    'cleaners' => $cleanerOptions[$s->property_id] ?? [],
                                                                ]))">Reassign</button>
                                                        @endif
                                                        <a href="{{ route('sessions.show', $s) }}"
                                                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                                            View &rarr;
                                                        </a>
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                    @endif
                </div>
            </div>


            {{-- Quick tips for HKs --}}
            @if ($acting === 'housekeeper')
                <div class="mt-4 text-xs text-gray-600 dark:text-gray-400">
                    Tip: Start a session from this list → GPS confirm → complete room tasks → inventory → upload ≥8
                    photos per room → submit.
                </div>
            @endif
        </div>
    </div>
    <x-drawer title="Assign cleaner" open="assignOpen" on-close="closeAssign()" id="calendar-assign-drawer">
        <form class="space-y-5" @submit.prevent="submit()">
            <div class="rounded-lg bg-slate-50 p-3 text-sm">
                <p x-show="!form.picking" class="font-semibold text-slate-900" x-text="form.property_name"></p>
                <div x-show="form.picking" class="mb-2">
                    <label for="cal-assign-property" class="field-label">Property</label>
                    <select id="cal-assign-property" class="input mt-1 w-full" x-model.number="form.property_id" @change="pickProperty()">
                        <option value="">Select a property</option>
                        <template x-for="p in properties" :key="p.id">
                            <option :value="p.id" :disabled="!p.has_owner" x-text="p.name + (p.has_owner ? '' : ' (no owner)')"></option>
                        </template>
                    </select>
                </div>
                <p class="text-slate-600" x-text="form.date"></p>
            </div>
            <div>
                <label for="cal-assign-cleaner" class="field-label">Cleaner</label>
                <select id="cal-assign-cleaner" class="input mt-1 w-full" x-model.number="form.housekeeper_id" required>
                    <option value="">Select a cleaner</option>
                    <template x-for="cleaner in form.cleaners" :key="cleaner.id">
                        <option :value="cleaner.id" x-text="cleaner.name"></option>
                    </template>
                </select>
                <p x-show="form.property_id && form.cleaners.length === 0" class="mt-2 text-sm text-amber-700">No active cleaners are assigned to this property. Assign a cleaner to the property first.</p>
            </div>
            <div>
                <label for="cal-assign-time" class="field-label">Scheduled time</label>
                <input id="cal-assign-time" type="time" class="input mt-1 w-full" x-model="form.scheduled_time">
            </div>
            <div x-show="conflictsActive" x-cloak role="alert" class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-semibold">This assignment has conflicts:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <template x-for="c in conflicts" :key="c.message"><li x-text="c.message"></li></template>
                </ul>
                <p class="mt-2 text-amber-800">Review them, then choose Assign anyway or change the cleaner, date or time.</p>
            </div>
            <p x-show="error" x-text="error" role="alert" class="text-sm font-medium text-red-700"></p>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" @click="closeAssign()" :disabled="saving">Cancel</button>
                <button type="button" class="btn-primary" x-show="conflictsActive" @click="submit(true)" :disabled="saving">
                    <span x-text="saving ? 'Saving...' : 'Assign anyway'"></span>
                </button>
                <button type="submit" class="btn-primary" x-show="!conflictsActive" :disabled="saving || !form.housekeeper_id || form.cleaners.length === 0">
                    <span x-text="saving ? 'Saving...' : 'Save assignment'"></span>
                </button>
            </div>
        </form>
    </x-drawer>

    <div x-data="calendarAddGuest(@js($pickerList))" @open-add-guest.window="openAdd()">
        <x-drawer title="Add guest" open="addOpen" on-close="closeAdd()" id="calendar-add-guest-drawer">
            <form class="space-y-4" @submit.prevent="submit()">
                <div>
                    <label for="cal-ag-res" class="field-label">Reservation ID (Airbnb/VRBO) <span class="text-red-600">*</span></label>
                    <input id="cal-ag-res" type="text" class="input mt-1 w-full" x-model="form.reservation_id" placeholder="Required, from Airbnb/VRBO" required>
                </div>
                <div>
                    <label for="cal-ag-platform" class="field-label">Booking platform</label>
                    <select id="cal-ag-platform" class="input mt-1 w-full" x-model="form.booking_platform">
                        @foreach(\App\Models\Booking::PLATFORMS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="cal-ag-name" class="field-label">Guest name <span class="text-red-600">*</span></label>
                    <input id="cal-ag-name" type="text" class="input mt-1 w-full" x-model="form.guest_name" placeholder="Jordan Taylor" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="cal-ag-in" class="field-label">Check-in <span class="text-red-600">*</span></label>
                        <input id="cal-ag-in" type="date" class="input mt-1 w-full" x-model="form.check_in_date" required>
                    </div>
                    <div>
                        <label for="cal-ag-out" class="field-label">Check-out <span class="text-red-600">*</span></label>
                        <input id="cal-ag-out" type="date" class="input mt-1 w-full" x-model="form.check_out_date" required>
                    </div>
                </div>
                <div>
                    <label for="cal-ag-property" class="field-label">Property <span class="text-red-600">*</span></label>
                    <select id="cal-ag-property" class="input mt-1 w-full" x-model.number="form.property_id" required>
                        <option value="">Select a property</option>
                        <template x-for="p in properties" :key="p.id">
                            <option :value="p.id" x-text="p.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label for="cal-ag-idtype" class="field-label">ID type <span class="text-red-600">*</span></label>
                    <select id="cal-ag-idtype" class="input mt-1 w-full" x-model="form.id_type" required>
                        <option value="state_id">State ID</option>
                        <option value="passport">Passport</option>
                    </select>
                </div>
                <label class="field-label flex items-center gap-2">
                    <input type="checkbox" x-model="form.photo_id_received">
                    <span>Photo ID Already Received</span>
                </label>
                <p class="field-help -mt-2">If enabled, the guest will not be asked to upload a photo ID during check-in.</p>
                <div>
                    <label for="cal-ag-notes" class="field-label">Internal notes</label>
                    <textarea id="cal-ag-notes" rows="3" class="textarea mt-1 w-full" x-model="form.notes" placeholder="Arrival requests, internal reminders, owner notes..."></textarea>
                </div>
                <p x-show="error" x-text="error" role="alert" class="text-sm font-medium text-red-700"></p>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="closeAdd()" :disabled="saving">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="saving">
                        <span x-text="saving ? 'Saving...' : 'Save guest'"></span>
                    </button>
                </div>
            </form>
        </x-drawer>
    </div>
    </div>

    <div x-data="calendarGuest()" @open-guest.window="openGuest($event.detail)">
        <x-drawer title="Guest" open="guestOpen" on-close="closeGuest()" id="calendar-guest-drawer">
            <template x-if="guestOpen">
                <iframe :src="url" :title="title" class="w-full rounded border border-slate-200" style="height: calc(100vh - 8rem);"></iframe>
            </template>
        </x-drawer>
    </div>

    <script>
            window.calendarGuest = () => ({
                guestOpen: false,
                url: '',
                title: 'Guest',
                openGuest(d) {
                    this.url = d.url;
                    this.title = d.title || 'Guest';
                    this.guestOpen = true;
                },
                async closeGuest() {
                    this.guestOpen = false;
                    this.url = '';
                    try {
                        const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const fresh = doc.getElementById('calendar-root');
                        const current = document.getElementById('calendar-root');
                        if (!fresh || !current) { window.location.reload(); return; }
                        current.innerHTML = fresh.innerHTML;
                    } catch (e) {
                        window.location.reload();
                    }
                }
            });
    </script>
    <script>
            window.calendarAddGuest = (properties = []) => ({
                properties: properties,
                addOpen: false,
                saving: false,
                error: '',
                form: {},
                blank() {
                    return { reservation_id: '', booking_platform: 'Airbnb', guest_name: '', check_in_date: '', check_out_date: '', property_id: '', id_type: 'state_id', photo_id_received: false, notes: '' };
                },
                openAdd() {
                    this.form = this.blank();
                    this.error = '';
                    this.addOpen = true;
                },
                closeAdd() {
                    if (!this.saving) this.addOpen = false;
                },
                async refresh() {
                    const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                    const fresh = doc.getElementById('calendar-root');
                    const current = document.getElementById('calendar-root');
                    if (!fresh || !current) { window.location.reload(); return; }
                    current.innerHTML = fresh.innerHTML;
                },
                async submit() {
                    this.saving = true;
                    this.error = '';
                    try {
                        const f = this.form;
                        const response = await fetch('{{ route('admin.guests.store') }}', {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                            },
                            body: JSON.stringify({
                                reservation_id: f.reservation_id,
                                booking_platform: f.booking_platform || null,
                                guest_name: f.guest_name,
                                check_in_date: f.check_in_date,
                                check_out_date: f.check_out_date,
                                property_id: f.property_id,
                                id_type: f.id_type,
                                photo_id_received: !!f.photo_id_received,
                                status: 'pending',
                                notes: f.notes || null
                            })
                        });
                        const result = await response.json();
                        if (!response.ok) {
                            const first = result.errors ? Object.values(result.errors).flat()[0] : null;
                            throw new Error(first || result.message || 'The guest could not be saved.');
                        }
                        await this.refresh();
                        this.addOpen = false;
                    } catch (e) {
                        this.error = e.message || 'The guest could not be saved.';
                    } finally {
                        this.saving = false;
                    }
                }
            });
    </script>
    <script>
            window.calendarAssign = (properties = []) => ({
                    properties: properties,
                    assignOpen: false,
                    saving: false,
                    error: '',
                    form: { property_id: '', property_name: '', date: '', session_id: null, housekeeper_id: '', scheduled_time: '', cleaners: [] },
                    openAssign(data) {
                        this.form = { ...data, housekeeper_id: data.housekeeper_id ?? '', scheduled_time: data.scheduled_time ?? '' };
                        this.error = '';
                        this.assignOpen = true;
                    },
                    closeAssign() {
                        if (!this.saving) this.assignOpen = false;
                    },
                    openCreate(date) {
                        this.form = { property_id: '', property_name: '', date: date, session_id: null, housekeeper_id: '', scheduled_time: '', cleaners: [], picking: true };
                        this.error = '';
                        this.assignOpen = true;
                    },
                    pickProperty() {
                        const p = this.properties.find(x => x.id === this.form.property_id);
                        this.form.cleaners = p ? p.cleaners : [];
                        this.form.housekeeper_id = '';
                        this.form.property_name = p ? p.name : '';
                    },
                    async changeStatus(id, el, old) {
                        const status = el.value;
                        if (status === old) return;
                        if (status === 'completed' && !confirm('Mark this cleaning as completed without the cleaner finishing the checklist? This is logged as a manual change.')) {
                            el.value = old;
                            return;
                        }
                        el.disabled = true;
                        try {
                            const response = await fetch('{{ route('admin.cleaning-jobs.calendar-status') }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                },
                                body: JSON.stringify({ session_id: id, status: status })
                            });
                            const result = await response.json();
                            if (!response.ok) {
                                const first = result.errors ? Object.values(result.errors).flat()[0] : null;
                                throw new Error(first || result.message || 'The status could not be changed.');
                            }
                            await this.refresh();
                        } catch (e) {
                            el.value = old;
                            alert(e.message || 'The status could not be changed.');
                        } finally {
                            el.disabled = false;
                        }
                    },
                    conflicts: [],
                    conflictKey: '',
                    conflictFormKey() { return JSON.stringify([this.form.property_id, this.form.date, this.form.housekeeper_id, this.form.scheduled_time || '']); },
                    get conflictsActive() { return this.conflicts.length > 0 && this.conflictKey === this.conflictFormKey(); },
                    async refresh() {
                        const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                        const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
                        const fresh = doc.getElementById('calendar-root');
                        const current = document.getElementById('calendar-root');
                        if (!fresh || !current) { window.location.reload(); return; }
                        current.innerHTML = fresh.innerHTML;
                    },
                    async submit(confirm = false) {
                        this.saving = true;
                        this.error = '';
                        this.conflicts = [];
                        try {
                            const response = await fetch('{{ route('admin.cleaning-jobs.calendar-assign') }}', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                },
                                body: JSON.stringify({
                                    property_id: this.form.property_id,
                                    scheduled_date: this.form.date,
                                    session_id: this.form.session_id,
                                    housekeeper_id: this.form.housekeeper_id,
                                    scheduled_time: this.form.scheduled_time || null,
                                    confirm_conflicts: confirm
                                })
                            });
                            const result = await response.json();
                            if (response.status === 409 && result.needs_confirmation) {
                                this.conflicts = result.conflicts || [];
                                this.conflictKey = this.conflictFormKey();
                                return;
                            }
                            if (!response.ok) {
                                const first = result.errors ? Object.values(result.errors).flat()[0] : null;
                                throw new Error(first || result.message || 'The cleaner could not be assigned.');
                            }
                            await this.refresh();
                            this.assignOpen = false;
                        } catch (e) {
                            this.error = e.message || 'The cleaner could not be assigned.';
                        } finally {
                            this.saving = false;
                        }
                    }
                });
    </script>
</x-app-layout>
