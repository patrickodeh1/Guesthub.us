<div class="flex items-center gap-3 px-4 py-3 transition hover:bg-slate-50">
    <a href="{{ route('admin.guests.show', $booking) }}" class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            <img src="{{ $booking->property->heroImageUrl() }}" alt="" class="h-6 w-9 shrink-0 rounded object-cover">
            <span class="truncate text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $booking->property->name }}</span>
        </div>
        <p class="mt-1 truncate font-semibold text-slate-950">{{ $booking->guest_name }}</p>
        @php
            $ctx = $context ?? null;
            $nights = max(0, (int) $booking->check_in_date->copy()->startOfDay()->diffInDays($booking->check_out_date->copy()->startOfDay()));
            $inToday = $booking->check_in_date->toDateString() === $today && ! $booking->isMarkedCheckedIn();
            $inTomorrow = $ctx === 'tomorrow' && ! $booking->isMarkedCheckedIn();
        @endphp
        @if(! empty($pinned) && $booking->priorityReason())
            <p class="truncate text-sm font-semibold text-amber-700">{{ $booking->priorityReason() }}</p>
        @endif
        @if($ctx === 'today' && $inToday)
            <p class="truncate text-sm text-slate-600">Checking in today for {{ $nights }} {{ $nights === 1 ? 'night' : 'nights' }}</p>
        @elseif($inTomorrow)
            <p class="truncate text-sm text-slate-600">Checking in tomorrow for {{ $nights }} {{ $nights === 1 ? 'night' : 'nights' }}</p>
        @else
            <p class="truncate text-sm text-slate-600">{!! $booking->dashboardArrivalLine($today) !!}</p>
        @endif
        @php $acct = \App\Services\CleanerAccountability::forBooking($booking, $today); @endphp
        @if($acct)
            <p class="truncate text-xs font-semibold {{ $acct['tone'] === 'ok' ? 'text-emerald-700' : ($acct['tone'] === 'warn' ? 'text-amber-700' : 'text-slate-500') }}">{{ $acct['text'] }}</p>
        @endif

    </a>
    @if($booking->phone)
        <a href="tel:{{ \App\Support\PhoneFormatter::toTelUri($booking->phone) }}" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700" title="Call guest" aria-label="Call {{ $booking->guest_name }}">
            <x-icon name="contact-guest-services" class="h-5 w-5" />
        </a>
    @endif
    <a href="{{ route('admin.guests.show', $booking) }}" class="badge badge-{{ $booking->effectiveStatus() }} shrink-0">{{ $booking->statusLabel() }}</a>
</div>
