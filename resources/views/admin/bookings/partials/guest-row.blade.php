<tr>
    <td><a class="font-semibold text-slate-950 hover:text-teal-800" href="{{ route('admin.guests.show', $booking) }}">{{ $booking->guest_name }}</a></td>
    <td>{{ $booking->property->name }}</td>
    <td>{{ $booking->stayRangeLabel() }}</td>
    <td><span class="badge badge-{{ $booking->effectiveStatus() }}">{{ $booking->statusLabel() }}</span></td>
    <td><div class="flex items-center justify-end gap-2">
        <div class="relative" data-row-menu>
            <button type="button" class="btn-secondary gap-2" onclick="toggleRowMenu(this)"><x-icon name="more-vertical" class="h-4 w-4" />Actions</button>
            <div data-row-menu-panel class="hidden absolute right-0 z-10 mt-1 w-56 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                <a href="{{ route('admin.guests.show', $booking) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"><x-icon name="eye" class="h-4 w-4" />View / Edit</a>
                <button type="button" onclick="copyGuestUrl(this, '{{ $booking->publicUrl() }}')" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="copy" class="h-4 w-4" /><span data-copy-label>Copy Guest URL</span></button>

                <div class="my-1 border-t border-slate-100"></div>

                @if(! $booking->photo_id_received)
                    <form method="post" action="{{ route('admin.guests.mark-id', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="upload" class="h-4 w-4" />Mark Photo ID Received</button></form>
                    <form method="post" action="{{ route('admin.guests.bypass-vehicle-info', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="car" class="h-4 w-4" />Bypass Vehicle Info</button></form>
                @endif
                @if(($booking->photo_id_path || $booking->photo_id_back_path) && ! $booking->isApproved())
                    <form method="post" action="{{ route('admin.guests.approve', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="check" class="h-4 w-4" />Approve for Check-In</button></form>
                @endif
                @if($booking->isApproved() && ! $booking->isBackgroundCheckComplete())
                    <form method="post" action="{{ route('admin.guests.background-check', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="shield-alert" class="h-4 w-4" />Mark {{ \App\Models\Setting::getValue('background_check_step_name', 'Background Check') }} Complete</button></form>
                @endif
                @if($booking->isBackgroundCheckComplete() && ! $booking->isDepositVerified())
                    <form method="post" action="{{ route('admin.guests.deposit-verified', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="lock" class="h-4 w-4" />Mark Deposit Verified</button></form>
                @endif
                @if(! $booking->gps_verified)
                    <form method="post" action="{{ route('admin.guests.override-gps', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="map" class="h-4 w-4" />Override GPS Verification</button></form>
                @endif
                @if(! $booking->isCheckedIn())
                    <form method="post" action="{{ route('admin.guests.override', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="contact-guest-services" class="h-4 w-4" />Manually Mark Checked In</button></form>
                @endif
                @if($booking->isCheckedIn() && ! $booking->checked_out_at)
                    <form method="post" action="{{ route('admin.guests.override-checkout', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="contact-guest-services" class="h-4 w-4" />Manually Mark Checked Out</button></form>
                @endif

                <div class="my-1 border-t border-slate-100"></div>

                @if($booking->archived_at)
                    <form method="post" action="{{ route('admin.guests.unarchive', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="refresh" class="h-4 w-4" />Restore</button></form>
                @else
                    <form method="post" action="{{ route('admin.guests.archive', $booking) }}">@csrf<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"><x-icon name="folder" class="h-4 w-4" />Archive</button></form>
                @endif
                <form method="post" action="{{ route('admin.guests.destroy', $booking) }}" onsubmit="return confirm('Delete this guest? This cannot be undone.')">@csrf @method('delete')<button class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"><x-icon name="delete" class="h-4 w-4" />Delete</button></form>
            </div>
        </div>
    </div></td>
</tr>
