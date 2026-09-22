<div class="relative min-h-[112px] border-b border-slate-100 px-4 py-3 pr-16 last:border-0">
    @php
        $checkinToday = $booking->check_in_date?->isToday() ?? false;
        $checkoutToday = $booking->check_out_date?->isToday() ?? false;
    @endphp
    <div class="min-w-0 pr-[45%]">
        <a class="block break-words pr-2 text-[17px] font-bold leading-6 text-slate-950 hover:text-teal-800" href="{{ route('admin.guests.show', $booking) }}">{{ $booking->guest_name }}</a>
        <p class="break-words text-sm font-normal italic text-slate-500">{{ $booking->property->name }}</p>
        <p class="text-sm text-slate-600">
            {{ $booking->dateRangeOnly() }}
            @if($booking->nightsLabel())
                &middot; {{ $booking->nightsLabel() }}
            @endif
            @if(! $booking->isMarkedCheckedIn() && ! $booking->checked_out_at)
                {{-- Not yet checked in: always show "checks in" language,
                     regardless of section -- never "checks out" until the
                     guest is actually marked checked in. --}}
                &middot; {{ $booking->weekCardArrivalLabel() }}
            @else
                &middot; {{ $booking->weekCardDynamicLabel() }}
            @endif
        </p>
        <p class="mt-0.5 flex items-center gap-1.5 text-sm font-medium text-slate-700">
            <x-icon name="clock" class="h-3.5 w-3.5 shrink-0 text-slate-400" />
            @if($checkinToday && ! $checkoutToday)
                <span>Check-in {{ $booking->effectiveCheckinTimeFormatted() }}</span>
            @elseif($checkoutToday && ! $checkinToday)
                <span>Check-out {{ $booking->effectiveCheckoutTimeFormatted() }}</span>
            @else
                <span>Check-in {{ $booking->effectiveCheckinTimeFormatted() }} &middot; Check-out {{ $booking->effectiveCheckoutTimeFormatted() }}</span>
            @endif
        </p>
    </div>

    <div class="absolute right-4 top-3 max-w-[45%] text-right">
        @if($context === 'upcoming' || ($context === 'this-week' && $booking->daysUntilCheckIn() > 0 && ! $booking->isMarkedCheckedIn()))
            <span class="badge badge-arrival-countdown whitespace-normal">{{ $booking->arrivalCountdownLabel() }}</span>
        @else
            <span class="badge badge-{{ $booking->effectiveStatus() }} whitespace-normal">{{ $booking->statusLabel() }}</span>
        @endif
    </div>

    <div class="absolute bottom-3 right-4" data-row-menu>
        <button type="button" class="btn-secondary !h-8 !min-h-8 !w-8 !p-1" onclick="toggleRowMenu(this)" aria-label="Actions"><x-icon name="more-vertical" class="h-4 w-4" /></button>
        <div data-row-menu-panel class="hidden absolute right-0 z-10 mt-1 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
            <a href="{{ route('admin.guests.show', $booking) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"><x-icon name="eye" class="h-4 w-4" />View / Edit</a>
            <button type="button" onclick="copyGuestUrl(this, '{{ $booking->publicUrl() }}')" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="copy" class="h-4 w-4" /><span data-copy-label>Copy Guest URL</span></button>

            @if(! $booking->isCancelled() && (($booking->isApproved() && ! $booking->isBackgroundCheckComplete()) || ($booking->isBackgroundCheckComplete() && ! $booking->isDepositVerified()) || ! $booking->gps_verified))
                <div class="my-1 border-t border-slate-100"></div>

                @if($booking->isApproved() && ! $booking->isBackgroundCheckComplete())
                    <form method="post" action="{{ route('admin.guests.background-check', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="shield-alert" class="h-4 w-4" />Background Passed</button></form>
                @endif
                @if($booking->isBackgroundCheckComplete() && ! $booking->isDepositVerified())
                    <form method="post" action="{{ route('admin.guests.deposit-verified', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="lock" class="h-4 w-4" />Deposit Verified</button></form>
                @endif
                @if(! $booking->gps_verified)
                    <form method="post" action="{{ route('admin.guests.override-gps', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="map" class="h-4 w-4" />Override GPS</button></form>
                @endif
            @endif

            <div class="my-1 border-t border-slate-100"></div>

            @if($booking->archived_at)
                <form method="post" action="{{ route('admin.guests.unarchive', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="refresh" class="h-4 w-4" />Restore</button></form>
            @else
                <form method="post" action="{{ route('admin.guests.archive', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="folder" class="h-4 w-4" />Archive</button></form>
            @endif

            @unless($booking->isCancelled())
                <div class="my-1 border-t border-slate-100"></div>
                <form method="post" action="{{ route('admin.guests.update-status', $booking) }}" onsubmit="return confirm('Cancel this reservation? If check-in is within 30 days, a cancellation fee applies and it will stay active but locked. Otherwise it will be archived immediately.')">
                    @csrf
                    <input type="hidden" name="status" value="cancelled">
                    <button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"><x-icon name="x" class="h-4 w-4" />Cancel Reservation</button>
                </form>
            @endunless

            <form method="post" action="{{ route('admin.guests.destroy', $booking) }}" onsubmit="return confirm('Delete this guest? This cannot be undone.')">@csrf @method('delete')<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"><x-icon name="delete" class="h-4 w-4" />Delete</button></form>
        </div>
    </div>
</div>
