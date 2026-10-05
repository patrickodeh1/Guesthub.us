@php
    $b = $row['booking'];
    $next = $row['next'];
    $session = $row['session'];
@endphp
<div id="checkout-row-{{ $b->property_id }}-{{ $row['date']->format('Ymd') }}" class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center {{ $row['covered'] ? '' : 'border-l-4 border-red-400 bg-red-50/50' }}">
    {{-- Property / guest / checkout --}}
    <div class="min-w-0 flex-1">
        <p class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $row['property']?->internal_display_name }}</p>
        <a href="{{ route('admin.guests.show', $b) }}" class="mt-0.5 block truncate font-semibold text-slate-950 hover:underline">{{ $b->guest_name }}</a>
        @if($row['overlap'])
            <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-800" title="{{ implode(', ', $row['guest_names']) }}">Overlapping bookings ({{ count($row['guest_names']) }})</span>
        @endif
        <p class="text-sm text-slate-600">
            Checkout {{ $row['date']->format('D, M j') }}
            @if($row['checkout_time']) &middot; {{ $row['checkout_time'] }} @endif
        </p>
        @if($session)
            <p class="text-xs text-slate-500">Cleaning tagged to: <span class="font-semibold">{{ $session->no_guest ? 'No guest' : ($session->booking?->guest_name ?? 'Untagged') }}</span></p>
        @endif
    </div>

    {{-- Next guest --}}
    <div class="min-w-0 flex-1 text-sm">
        @if($next)
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Next guest</p>
            <a href="{{ route('admin.guests.show', $next) }}" class="block truncate font-medium text-slate-900 hover:underline">{{ $next->guest_name }}</a>
            <p class="text-slate-600">
                {{ $next->check_in_date->format('D, M j') }}@if($row['next_time']) &middot; {{ $row['next_time'] }}@endif
            </p>
            @if($row['same_day'])
                <span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">Same-day turnover</span>
            @endif
        @else
            <p class="text-slate-400">No next guest</p>
        @endif
    </div>

    {{-- Cleaning --}}
    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2 sm:justify-end">
        @if($row['covered'])
            <div class="mr-auto min-w-0 sm:mr-0 sm:text-right">
                <p class="truncate text-sm font-semibold text-slate-900">{{ $row['cleaner'] }}</p>
                <p class="text-xs text-slate-500">
                    {{ \Illuminate\Support\Str::headline($session->status ?? 'scheduled') }}
                    @if($session->scheduled_time) &middot; {{ $session->scheduled_time->format('g:i A') }} @endif
                </p>
            </div>
            @if(($session->assignment_status ?? null) === 'pending_confirmation')
                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-800">Awaiting reply</span>
            @else
                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">Covered</span>
            @endif
            <button type="button" class="btn-secondary text-sm"
                @click="openQuickAssign(@js([
                    'property_id' => $b->property_id,
                    'property_name' => $row['property']?->name,
                    'date' => $row['date']->toDateString(),
                    'session_id' => $session?->id,
                    'housekeeper_id' => $session?->housekeeper_id,
                    'scheduled_time' => $session?->scheduled_time?->format('H:i'),
                    'cleaners' => $row['cleaner_options'],
                ]))">Reassign</button>
            <a href="{{ route('sessions.show', $session) }}" class="text-sm font-semibold text-[var(--theme-primary)] underline">Job</a>
        @else
            <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">Unassigned</span>
            <button type="button" class="btn-primary text-sm"
                @click="openQuickAssign(@js([
                    'property_id' => $b->property_id,
                    'property_name' => $row['property']?->name,
                    'date' => $row['date']->toDateString(),
                    'session_id' => $session?->id,
                    'housekeeper_id' => $session?->housekeeper_id,
                    'scheduled_time' => $session?->scheduled_time?->format('H:i'),
                    'cleaners' => $row['cleaner_options'],
                ]))">Assign cleaner</button>
        @endif
    </div>
</div>
