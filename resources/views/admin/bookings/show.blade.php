<x-admin-layout title="Guest Details">
    <a href="{{ route('admin.guests.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800">
        <x-icon name="arrow-left" class="h-4 w-4" />
        Back to Guests
    </a>

    @php
        $checkinNeedsReview = $booking->checkin_time_status === 'pending';
        $checkoutNeedsReview = $booking->checkout_time_status === 'pending';
    @endphp

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-4 lg:items-start">
    <div class="lg:col-span-3">
    <section class="card card-pad mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="page-title !mt-0.5 text-2xl font-bold">{{ $booking->guest_name }}</h1>
                <p class="page-subtitle !mt-1">{{ $booking->property->name }}</p>
                <p class="page-subtitle !mt-1">{{ $booking->stayRangeLabel() }}</p>
                @if($booking->checkinTimePreferenceFormatted() || $booking->checkoutTimePreferenceFormatted() || $checkinNeedsReview || $checkoutNeedsReview)
                    <div class="mt-3 flex flex-col gap-2 text-sm">
                        @if($booking->checkinTimePreferenceFormatted() || $checkinNeedsReview)
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested check-in</span>
                                <span class="font-semibold text-slate-950">{{ $booking->checkinTimePreferenceFormatted() ?? 'Not set' }}</span>
                                @if($checkinNeedsReview)
                                    <span class="badge badge-pending">Needs review</span>
                                    <form method="POST" action="{{ route('admin.guests.time-preference.update', [$booking, 'checkin']) }}" class="inline" data-confirm-title="Approve check-in time?" data-confirm="Approve this requested check-in time? The guest's arrival details will use this time. This approval does not charge the guest. To charge for early check-in, configure the early check-in tier and choose Charged upfront, or choose Deducted from hold to take it from the incidentals hold at checkout.">@csrf<input type="hidden" name="decision" value="approved"><button type="submit" title="Approve check-in time" class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100"><x-icon name="check" class="h-3.5 w-3.5" /></button></form>
                                    <form method="POST" action="{{ route('admin.guests.time-preference.update', [$booking, 'checkin']) }}" class="inline" data-confirm-title="Decline check-in time?" data-confirm="Decline this requested check-in time? The property's standard check-in time will remain in effect.">@csrf<input type="hidden" name="decision" value="denied"><button type="submit" title="Reject check-in time" class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"><x-icon name="x" class="h-3.5 w-3.5" /></button></form>
                                @elseif($booking->checkin_time_status === 'approved')
                                    <span class="badge badge-active">Approved</span>
                                @elseif($booking->checkin_time_status === 'denied')
                                    <span class="badge badge-danger">Declined</span>
                                @endif
                            </div>
                        @endif
                        @if($booking->checkoutTimePreferenceFormatted() || $checkoutNeedsReview)
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Requested check-out</span>
                                <span class="font-semibold text-slate-950">{{ $booking->checkoutTimePreferenceFormatted() ?? 'Not set' }}</span>
                                @if($checkoutNeedsReview)
                                    <span class="badge badge-pending">Needs review</span>
                                    <form method="POST" action="{{ route('admin.guests.time-preference.update', [$booking, 'checkout']) }}" class="inline" data-confirm-title="Approve check-out time?" data-confirm="Approve this requested check-out time? The guest's stay will use this time. This approval does not charge the guest. Configure the late checkout type, hours, and charge override if needed; late checkout is deducted from the incidentals hold at checkout rather than billed separately.">@csrf<input type="hidden" name="decision" value="approved"><button type="submit" title="Approve check-out time" class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100"><x-icon name="check" class="h-3.5 w-3.5" /></button></form>
                                    <form method="POST" action="{{ route('admin.guests.time-preference.update', [$booking, 'checkout']) }}" class="inline" data-confirm-title="Decline check-out time?" data-confirm="Decline this requested check-out time? The property's standard check-out time will remain in effect.">@csrf<input type="hidden" name="decision" value="denied"><button type="submit" title="Reject check-out time" class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-red-200 bg-red-50 text-red-700 hover:bg-red-100"><x-icon name="x" class="h-3.5 w-3.5" /></button></form>
                                @elseif($booking->checkout_time_status === 'approved')
                                    <span class="badge badge-active">Approved</span>
                                @elseif($booking->checkout_time_status === 'denied')
                                    <span class="badge badge-danger">Declined</span>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if($booking->phone)
                    <a href="tel:{{ \App\Support\PhoneFormatter::toTelUri($booking->phone) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 hover:text-emerald-700" title="Call guest" aria-label="Call guest">
                        <x-icon name="contact-guest-services" class="h-5 w-5" />
                    </a>
                @endif
                <span class="badge badge-{{ $booking->effectiveStatus() }} px-3 py-1 text-sm">{{ $booking->statusLabel() }}</span>
                @unless($booking->isCancelled())
                <button type="button" title="Edit Guest Details" aria-label="Edit Guest Details" aria-expanded="false" onclick="toggleGuestDetailsEdit(this)" class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-800">
                    <x-icon name="edit" class="h-4 w-4" />
                </button>
                @endunless
            </div>
        </div>

        @if($booking->isCancelled())
            <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-bold">Cancelled by guest</p>
                <p class="mt-1">
                    Cancelled {{ $booking->localTimestamp($booking->cancelled_at)?->format('M j, Y g:i A') ?? '' }}.
                    @if($booking->cancellationFeeApplies())
                        This falls within the 30-day window before arrival, so a cancellation fee is owed. The reservation is locked — no edits, approvals, or check-in actions — until it is settled.
                    @else
                        Outside the 30-day window; the reservation is archived and no fee is owed.
                    @endif
                </p>
            </div>
        @endif

        {{-- Expandable edit section: replaces the old separate /edit page for
             existing bookings. Everything below is hidden by default so the
             header looks exactly like before until an admin opens it.
             Deliberately excludes parking_needed / early_checkin_tier /
             late_checkout_type / late_checkout_hours / late_checkout_actual_time
             -- those are charge-driving fields already represented in the
             Guest Details card below and get their own editor with the
             ledger work, not here. --}}
        @php
            $guestDetailsErrorFields = ['reservation_id', 'booking_platform', 'guest_name', 'phone', 'email', 'check_in_date', 'check_out_date', 'property_id', 'id_type', 'checkin_time_preference', 'checkout_time_preference', 'status', 'photo_id_received', 'notes'];
            $hasGuestDetailsErrors = $errors->hasAny($guestDetailsErrorFields);
        @endphp
        @unless($booking->isCancelled())
        <div id="guest-details-edit-panel" class="{{ $hasGuestDetailsErrors ? '' : 'hidden' }} mt-6 border-t border-slate-100 pt-6">
            <form method="post" action="{{ route('admin.guests.update', $booking) }}">
                @csrf @method('put')
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="field-label">Reservation ID (Airbnb/VRBO) <span class="text-red-600">*</span><input name="reservation_id" value="{{ old('reservation_id', $booking->reservation_id) }}" required class="input">@error('reservation_id')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="field-label">Booking platform<input name="booking_platform" list="booking-platform-options" value="{{ old('booking_platform', $booking->booking_platform) }}" placeholder="Airbnb, Vrbo, Booking.com…" class="input"><datalist id="booking-platform-options"><option value="Airbnb"></option><option value="Vrbo"></option><option value="Booking.com"></option><option value="Expedia"></option><option value="Direct"></option></datalist><span class="field-help">Shown to the guest on the payment screen ("Pay on …"). Auto-filled for channel-manager bookings.</span></label>
                    <label class="field-label">Guest name <span class="text-red-600">*</span><input name="guest_name" value="{{ old('guest_name', $booking->guest_name) }}" required class="input">@error('guest_name')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    <div class="field-label">
                        <span>Phone</span>
                        <div class="mt-1 flex gap-2">
                            <div class="relative w-28 shrink-0">
                                <button type="button" id="guest-phone-country-button" class="input flex items-center justify-between gap-1.5 px-2.5 text-left">
                                    <span id="guest-phone-country-label" class="flex items-center gap-1.5 text-sm font-medium"><span id="guest-phone-country-flag">🇺🇸</span><span id="guest-phone-country-dial">+1</span></span>
                                    <span aria-hidden="true" class="text-xs text-slate-400">▾</span>
                                </button>
                                <div id="guest-phone-country-menu" class="absolute left-0 top-full z-20 mt-1 hidden w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                                    <div class="border-b border-slate-100 p-2"><input type="text" id="guest-phone-country-search" placeholder="Search country or code" class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm" autocomplete="off"></div>
                                    <div id="guest-phone-country-list" class="max-h-56 overflow-y-auto"></div>
                                </div>
                            </div>
                            <input type="hidden" id="guest-phone-country-code" name="phone_country_code" value="+1">
                            <input id="guest-detail-phone-input" name="phone" value="{{ old('phone', \App\Support\PhoneFormatter::format($booking->phone)) }}" placeholder="(555) 555-0199" maxlength="18" class="input min-w-0 flex-1">
                        </div>
                    </div>
                    <label class="field-label">Email<input name="email" value="{{ old('email', $booking->email) }}" placeholder="guest@example.com" class="input">@error('email')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="field-label">Check-in <span class="text-red-600">*</span><input type="date" name="check_in_date" value="{{ old('check_in_date', optional($booking->check_in_date)->format('Y-m-d')) }}" required class="input">@error('check_in_date')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="field-label">Check-out <span class="text-red-600">*</span><input type="date" name="check_out_date" value="{{ old('check_out_date', optional($booking->check_out_date)->format('Y-m-d')) }}" required class="input">@error('check_out_date')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="field-label">Property <span class="text-red-600">*</span><select name="property_id" required class="input"><option value="" disabled @selected(!old('property_id', $booking->property_id))>Select a property...</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected(old('property_id', $booking->property_id)==$property->id)>{{ $property->name }}</option>@endforeach</select>@error('property_id')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                    <label class="field-label">ID type <span class="text-red-600">*</span><select name="id_type" required class="input"><option value="state_id" @selected(old('id_type', $booking->id_type ?: 'state_id')==='state_id')>State-issued ID (US guest)</option><option value="passport" @selected(old('id_type', $booking->id_type ?: 'state_id')==='passport')>Passport (international guest)</option></select></label>
                    <label class="field-label">Requested Check-in Time<input type="time" name="checkin_time_preference" value="{{ old('checkin_time_preference', $booking->checkin_time_preference) }}" class="input"></label>
                    <label class="field-label">Requested Check-out Time<input type="time" name="checkout_time_preference" value="{{ old('checkout_time_preference', $booking->checkout_time_preference) }}" class="input"></label>
                    <label class="field-label">Status<select name="status" class="input">@foreach(['pending','pre_checkin_complete','awaiting_deposit','guest_approved','currently_hosting','checked_out'] as $status)<option value="{{ $status }}" @selected(old('status', $booking->status ?: 'pending')===$status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></label>
                    <label class="field-label flex items-center gap-2 md:col-span-2">
                        <input type="checkbox" name="photo_id_received" value="1" @checked(old('photo_id_received', $booking->photo_id_received))>
                        <span>Photo ID Already Received</span>
                    </label>
                    <label class="field-label md:col-span-2">Notes<textarea name="notes" rows="4" placeholder="Arrival requests, internal reminders, owner notes..." class="textarea">{{ old('notes', $booking->notes) }}</textarea></label>
                </div>
                <div class="mt-5 flex gap-3">
                    <button class="btn-primary">Save changes</button>
                    <button type="button" onclick="toggleGuestDetailsEdit(document.querySelector('[aria-label=\'Edit Guest Details\']'))" class="btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
        @endunless
    </section>
    </div>

        <div class="mt-3 grid content-start gap-3 lg:col-span-3 order-3 lg:order-none">
            {{-- Guest Details --}}
            <section class="card card-pad">
                <div class="flex items-center justify-between">
                    <h2 class="section-title">Guest Details</h2>
                </div>
                <dl class="mt-4 grid gap-x-10 text-sm sm:grid-cols-2">
                    @foreach([
                        ['receipt', 'Incidentals Charge', $booking->effectiveIncidentalsCharge() !== null ? '$'.number_format($booking->effectiveIncidentalsCharge(), 2) : 'Not set', 'The refundable hold charged to the guest before check-in. Uses this booking\'s override if set below, otherwise the property\'s default hold amount.'],
                        ['parking', 'Parking Charge', $booking->effectiveParkingCharge() !== null ? '$'.number_format($booking->effectiveParkingCharge(), 2) : 'Not set', 'Auto-calculated from the property\'s per-weekday parking rates across the guest\'s stay, unless overridden below.'],
                        ...(($booking->effectiveEarlyCheckinCharge() ?? 0) > 0 || $booking->early_checkin_tier ? [['calendar', $booking->earlyCheckinIsDeductedFromHold() ? 'Early Check-in Deduction' : 'Early Check-in Charge', '$'.number_format($booking->effectiveEarlyCheckinCharge() ?? 0, 2), $booking->earlyCheckinIsDeductedFromHold() ? 'Deducted from the incidentals hold at checkout, like late checkout.' : 'Billed to the guest as part of their pre-check-in total -- not deducted from the incidentals hold.']] : []),
                        ...(($booking->effectiveLateCheckoutCharge() ?? 0) > 0 || $booking->late_checkout_type ? [['clock', 'Late Checkout Deduction', '$'.number_format($booking->effectiveLateCheckoutCharge() ?? 0, 2).($booking->late_checkout_type ? ' ('.ucfirst($booking->late_checkout_type).')' : ''), 'Never billed to the guest separately -- this amount is deducted from their incidentals hold refund after checkout instead. See the Ledger card below.']] : []),
                    ] as [$icon, $label, $value, $help])
                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 break-inside-avoid last:border-0 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon :name="$icon" class="h-4 w-4 shrink-0 text-slate-400" />{{ $label }}<x-help :text="$help" /></span>
                            <span class="font-semibold text-slate-950 sm:text-right">{{ $value }}</span>
                        </div>
                    @endforeach

                    {{-- Editable billing fields: each row's own pencil turns
                         just that one field into an input in place, and the
                         pencil itself becomes the save action -- the card
                         never expands, collapses, or splits. All fields live
                         in one always-present form (locked via pointer-events
                         + tabindex rather than "disabled", so every field's
                         current value is still submitted no matter which one
                         you actually edited -- disabled inputs are dropped
                         from form submission entirely, which would silently
                         wipe every other field). --}}
                    @unless($booking->isCancelled())
                    <form method="post" action="{{ route('admin.guests.ledger.update', $booking) }}" class="contents">
                        @csrf @method('put')

                        @php
                            $ledgerErrorFields = ['parking_needed', 'parking_charge_override', 'incidentals_charge', 'early_checkin_tier', 'early_checkin_charge_override', 'early_checkin_billing_mode', 'late_checkout_type', 'late_checkout_hours', 'late_checkout_actual_time', 'late_checkout_charge_override'];
                        @endphp
                        @if($errors->hasAny($ledgerErrorFields))
                            <div class="border-b border-slate-100 py-3 sm:col-span-2">
                                <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                                    @foreach($ledgerErrorFields as $lef)
                                        @error($lef)<p>{{ $message }}</p>@enderror
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:col-start-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="parking" class="h-4 w-4 shrink-0 text-slate-400" />Guest needs parking<x-help text="Set by the guest during their own check-in -- read-only here, not editable by admin." /></span>
                            <span class="font-semibold sm:text-right">
                                @if($booking->parking_needed === true)
                                    <x-icon name="check" class="inline h-4 w-4 text-emerald-600" />
                                @elseif($booking->parking_needed === false)
                                    <x-icon name="x" class="inline h-4 w-4 text-red-600" />
                                @else
                                    <span class="text-slate-400">Not set</span>
                                @endif
                            </span>
                        </div>

                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="parking" class="h-4 w-4 shrink-0 text-slate-400" />Parking override<x-help text="Overrides the auto-calculated parking charge above. Leave blank to keep using the property's per-weekday rates." /></span>
                            <span class="flex items-center gap-2 sm:justify-end">
                                <input type="number" step="0.01" min="0" id="lf-parking_charge_override" name="parking_charge_override" value="{{ old('parking_charge_override', $booking->parking_charge_override) }}" placeholder="Auto ${{ number_format($booking->calculateParkingCharge() ?? 0, 2) }}" class="ledger-field pointer-events-none w-28 max-w-full border-none bg-transparent p-0 text-right font-semibold text-slate-950 placeholder:font-normal placeholder:text-slate-400" tabindex="-1">
                                <button type="button" id="lf-parking_charge_override-btn" onclick="unlockLedgerField('lf-parking_charge_override')" class="text-slate-400 hover:text-slate-700"><span class="lf-pencil"><x-icon name="edit" class="h-3.5 w-3.5" /></span><span class="lf-check hidden"><x-icon name="check" class="h-3.5 w-3.5" /></span></button>
                            </span>
                        </div>

                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="receipt" class="h-4 w-4 shrink-0 text-slate-400" />Incidentals hold override<x-help text="Overrides the property's default hold amount for just this booking. Raise this if early check-in + late checkout charges could exceed it, to leave a damage buffer." /></span>
                            <span class="flex items-center gap-2 sm:justify-end">
                                <input type="number" step="0.01" min="0" id="lf-incidentals_charge" name="incidentals_charge" value="{{ old('incidentals_charge', $booking->incidentals_charge) }}" placeholder="Default ${{ number_format($booking->property->required_incidentals_hold_amount ?? 0, 2) }}" class="ledger-field pointer-events-none w-28 max-w-full border-none bg-transparent p-0 text-right font-semibold text-slate-950 placeholder:font-normal placeholder:text-slate-400" tabindex="-1">
                                <button type="button" id="lf-incidentals_charge-btn" onclick="unlockLedgerField('lf-incidentals_charge')" class="text-slate-400 hover:text-slate-700"><span class="lf-pencil"><x-icon name="edit" class="h-3.5 w-3.5" /></span><span class="lf-check hidden"><x-icon name="check" class="h-3.5 w-3.5" /></span></button>
                            </span>
                        </div>

                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="calendar" class="h-4 w-4 shrink-0 text-slate-400" />Early check-in window<x-help text="Which early check-in time block this guest is approved for. Setting this bills the guest the property's rate for that window as part of their pre-check-in total." /></span>
                            <span class="flex items-center gap-2 sm:justify-end">
                                <select id="lf-early_checkin_tier" name="early_checkin_tier" class="ledger-field pointer-events-none max-w-full appearance-none border-none bg-transparent p-0 text-right font-semibold text-slate-950" tabindex="-1">
                                    <option value="" @selected(!old('early_checkin_tier', $booking->early_checkin_tier))>None</option>
                                    <option value="8am_12pm" @selected(old('early_checkin_tier', $booking->early_checkin_tier)==='8am_12pm' || old('early_checkin_tier', $booking->early_checkin_tier)==='8am')>8:00 AM - 12:00 PM</option>
                                    <option value="12pm_2pm" @selected(old('early_checkin_tier', $booking->early_checkin_tier)==='12pm_2pm' || old('early_checkin_tier', $booking->early_checkin_tier)==='12pm')>12:00 PM - 2:00 PM</option>
                                    <option value="2pm_4pm" @selected(old('early_checkin_tier', $booking->early_checkin_tier)==='2pm_4pm')>2:00 PM - 4:00 PM</option>
                                </select>
                                <button type="button" id="lf-early_checkin_tier-btn" onclick="unlockLedgerField('lf-early_checkin_tier')" class="text-slate-400 hover:text-slate-700"><span class="lf-pencil"><x-icon name="edit" class="h-3.5 w-3.5" /></span><span class="lf-check hidden"><x-icon name="check" class="h-3.5 w-3.5" /></span></button>
                            </span>
                        </div>

                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="calendar" class="h-4 w-4 shrink-0 text-slate-400" />Early check-in override<x-help text="Charges this exact amount instead of the property's rate for the selected window. Leave blank to use the auto-calculated rate." /></span>
                            <span class="flex items-center gap-2 sm:justify-end">
                                <input type="number" step="0.01" min="0" id="lf-early_checkin_charge_override" name="early_checkin_charge_override" value="{{ old('early_checkin_charge_override', $booking->early_checkin_charge_override) }}" placeholder="Auto ${{ number_format($booking->earlyCheckinCharge() ?? 0, 2) }}" class="ledger-field pointer-events-none w-28 max-w-full border-none bg-transparent p-0 text-right font-semibold text-slate-950 placeholder:font-normal placeholder:text-slate-400" tabindex="-1">
                                <button type="button" id="lf-early_checkin_charge_override-btn" onclick="unlockLedgerField('lf-early_checkin_charge_override')" class="text-slate-400 hover:text-slate-700"><span class="lf-pencil"><x-icon name="edit" class="h-3.5 w-3.5" /></span><span class="lf-check hidden"><x-icon name="check" class="h-3.5 w-3.5" /></span></button>
                            </span>
                        </div>

                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="calendar" class="h-4 w-4 shrink-0 text-slate-400" />Early check-in billing<x-help text="Charged upfront: added to the guest's pre-check-in payment. Deducted from hold: taken out of the incidentals hold at checkout (like late checkout), so the guest is refunded less instead of paying more now." /></span>
                            <span class="flex items-center gap-2 sm:justify-end">
                                <select id="lf-early_checkin_billing_mode" name="early_checkin_billing_mode" class="ledger-field pointer-events-none max-w-full appearance-none border-none bg-transparent p-0 text-right font-semibold text-slate-950" tabindex="-1">
                                    <option value="charge" @selected(old('early_checkin_billing_mode', $booking->early_checkin_billing_mode) === 'charge')>Charged upfront</option>
                                    <option value="deduct_from_hold" @selected(old('early_checkin_billing_mode', $booking->early_checkin_billing_mode) === 'deduct_from_hold')>Deducted from hold</option>
                                </select>
                                <button type="button" id="lf-early_checkin_billing_mode-btn" onclick="unlockLedgerField('lf-early_checkin_billing_mode')" class="text-slate-400 hover:text-slate-700"><span class="lf-pencil"><x-icon name="edit" class="h-3.5 w-3.5" /></span><span class="lf-check hidden"><x-icon name="check" class="h-3.5 w-3.5" /></span></button>
                            </span>
                        </div>

                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="clock" class="h-4 w-4 shrink-0 text-slate-400" />Late checkout billing<x-help text="Authorized: hours are set by admin below. Unauthorized: hours are calculated from the actual checkout time you enter. Either way, this is deducted from the incidentals hold -- never billed to the guest separately." /></span>
                            <span class="flex items-center gap-2 sm:justify-end">
                                <select id="lf-late_checkout_type" name="late_checkout_type" class="ledger-field pointer-events-none max-w-full appearance-none border-none bg-transparent p-0 text-right font-semibold text-slate-950" tabindex="-1">
                                    <option value="" @selected(!old('late_checkout_type', $booking->late_checkout_type))>Not applicable</option>
                                    <option value="authorized" @selected(old('late_checkout_type', $booking->late_checkout_type)==='authorized')>Authorized</option>
                                    <option value="unauthorized" @selected(old('late_checkout_type', $booking->late_checkout_type)==='unauthorized')>Unauthorized</option>
                                </select>
                                <button type="button" id="lf-late_checkout_type-btn" onclick="unlockLedgerField('lf-late_checkout_type')" class="text-slate-400 hover:text-slate-700"><span class="lf-pencil"><x-icon name="edit" class="h-3.5 w-3.5" /></span><span class="lf-check hidden"><x-icon name="check" class="h-3.5 w-3.5" /></span></button>
                            </span>
                        </div>

                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="clock" class="h-4 w-4 shrink-0 text-slate-400" />Late checkout hours (authorized)<x-help text="How many hours late checkout was authorized for. Only used when billing is set to Authorized." /></span>
                            <span class="flex items-center gap-2 sm:justify-end">
                                <input type="number" step="0.25" min="0" id="lf-late_checkout_hours" name="late_checkout_hours" value="{{ old('late_checkout_hours', $booking->late_checkout_hours) }}" placeholder="e.g. 2" class="ledger-field pointer-events-none w-20 max-w-full border-none bg-transparent p-0 text-right font-semibold text-slate-950 placeholder:font-normal placeholder:text-slate-400" tabindex="-1">
                                <button type="button" id="lf-late_checkout_hours-btn" onclick="unlockLedgerField('lf-late_checkout_hours')" class="text-slate-400 hover:text-slate-700"><span class="lf-pencil"><x-icon name="edit" class="h-3.5 w-3.5" /></span><span class="lf-check hidden"><x-icon name="check" class="h-3.5 w-3.5" /></span></button>
                            </span>
                        </div>

                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="clock" class="h-4 w-4 shrink-0 text-slate-400" />Actual checkout time (unauthorized)<x-help text="What time the guest actually left. Used to calculate unauthorized late-checkout hours against the property's standard checkout time -- separate from the automatic checkout timestamp." /></span>
                            <span class="flex items-center gap-2 sm:justify-end">
                                <input type="datetime-local" id="lf-late_checkout_actual_time" name="late_checkout_actual_time" value="{{ old('late_checkout_actual_time', optional($booking->localTimestamp($booking->late_checkout_actual_time))->format('Y-m-d\TH:i')) }}" class="ledger-field pointer-events-none max-w-full border-none bg-transparent p-0 text-right font-semibold text-slate-950" tabindex="-1">
                                <button type="button" id="lf-late_checkout_actual_time-btn" onclick="unlockLedgerField('lf-late_checkout_actual_time')" class="text-slate-400 hover:text-slate-700"><span class="lf-pencil"><x-icon name="edit" class="h-3.5 w-3.5" /></span><span class="lf-check hidden"><x-icon name="check" class="h-3.5 w-3.5" /></span></button>
                            </span>
                        </div>

                        <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                            <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="clock" class="h-4 w-4 shrink-0 text-slate-400" />Late checkout override<x-help text="Charges/deducts this exact amount instead of the calculated per-half-hour rate. Leave blank to use the auto-calculated amount." /></span>
                            <span class="flex items-center gap-2 sm:justify-end">
                                <input type="number" step="0.01" min="0" id="lf-late_checkout_charge_override" name="late_checkout_charge_override" value="{{ old('late_checkout_charge_override', $booking->late_checkout_charge_override) }}" placeholder="Auto ${{ number_format($booking->lateCheckoutCharge() ?? 0, 2) }}" class="ledger-field pointer-events-none w-28 max-w-full border-none bg-transparent p-0 text-right font-semibold text-slate-950 placeholder:font-normal placeholder:text-slate-400" tabindex="-1">
                                <button type="button" id="lf-late_checkout_charge_override-btn" onclick="unlockLedgerField('lf-late_checkout_charge_override')" class="text-slate-400 hover:text-slate-700"><span class="lf-pencil"><x-icon name="edit" class="h-3.5 w-3.5" /></span><span class="lf-check hidden"><x-icon name="check" class="h-3.5 w-3.5" /></span></button>
                            </span>
                        </div>
                    </form>
                    @endunless
                    <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="contact-guest-services" class="h-4 w-4 shrink-0 text-slate-400" />Checked In At</span>
                        <span class="font-semibold sm:text-right">{{ $booking->localTimestamp($booking->checked_in_at)?->format('M j, Y g:i A') ?? 'Not yet' }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="contact-guest-services" class="h-4 w-4 shrink-0 text-slate-400" />Checked Out At</span>
                        <span class="font-semibold sm:text-right">{{ $booking->localTimestamp($booking->checked_out_at)?->format('M j, Y g:i A') ?? 'Not yet' }}</span>
                    </div>
                </dl>
                @if($booking->photo_id_front_declined_reason)
                    <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800"><span class="font-semibold">Front ID decline reason:</span> {{ $booking->photo_id_front_declined_reason }}</div>
                @endif
                @if($booking->photo_id_back_declined_reason)
                    <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800"><span class="font-semibold">Back ID decline reason:</span> {{ $booking->photo_id_back_declined_reason }}</div>
                @endif
                @if($booking->notes)
                    <div class="mt-4 rounded-lg bg-slate-50 border border-slate-200 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Internal notes</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $booking->notes }}</p>
                    </div>
                @endif
            </section>

            {{-- Ledger: what's actually held vs. what's actually owed, so
                 admin can see at a glance what to refund from the
                 incidentals hold after checkout. View-only -- adjust the
                 underlying amounts in Guest Details above; this card just
                 shows the math. --}}
            <section class="card card-pad">
                <div class="flex items-center justify-between">
                    <h2 class="section-title">Ledger<x-help text="What's actually held vs. owed for this guest, so you can see at a glance what to refund from the incidentals hold after checkout." /></h2>
                </div>

                @if($booking->ledgerDeductionsExceedHold())
                    <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">
                        <span class="font-semibold">Heads up:</span> the hold deductions (${{ number_format($booking->holdDeductions(), 2) }}{{ $booking->earlyCheckinIsDeductedFromHold() ? ' — late checkout + early check-in' : ' — late checkout' }}) exceed the current incidentals hold (${{ number_format($booking->effectiveIncidentalsCharge() ?? 0, 2) }}). Consider raising the incidentals hold override in Guest Details so the deductions are covered, with some buffer left for any damages.
                    </div>
                @endif

                <dl class="mt-4 grid gap-x-10 text-sm sm:grid-cols-2">
                    <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="calculator" class="h-4 w-4 shrink-0 text-slate-400" />Charged before check-in<x-help text="{{ $booking->preCheckinChargeBreakdown() }}." /></span>
                        <span class="font-semibold text-slate-950 sm:text-right">${{ number_format($booking->calculatePreCheckinChargeCents() / 100, 2) }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="receipt" class="h-4 w-4 shrink-0 text-slate-400" />Incidentals hold (refundable)<x-help text="The refundable portion of the amount above. This is what the deductions below come out of." /></span>
                        <span class="font-semibold text-slate-950 sm:text-right">${{ number_format($booking->effectiveIncidentalsCharge() ?? 0, 2) }}</span>
                    </div>
                    <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="clock" class="h-4 w-4 shrink-0 text-slate-400" />Late checkout deduction<x-help text="Never billed to the guest separately -- this comes out of their incidentals hold instead, reducing what you refund after checkout." /></span>
                        <span class="font-semibold text-slate-950 sm:text-right">-${{ number_format($booking->effectiveLateCheckoutCharge() ?? 0, 2) }}</span>
                    </div>
                    @if($booking->earlyCheckinIsDeductedFromHold())
                    <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <span class="flex items-center gap-2.5 text-slate-500"><x-icon name="calendar" class="h-4 w-4 shrink-0 text-slate-400" />Early check-in deduction<x-help text="This booking's early check-in is set to be deducted from the incidentals hold at checkout (not billed upfront)." /></span>
                        <span class="font-semibold text-slate-950 sm:text-right">-${{ number_format($booking->effectiveEarlyCheckinCharge() ?? 0, 2) }}</span>
                    </div>
                    @endif
                    <div class="flex flex-col gap-1 border-b border-slate-100 py-3 sm:col-span-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <span class="flex items-center gap-2.5 font-semibold text-slate-950"><x-icon name="receipt" class="h-4 w-4 shrink-0 text-slate-400" />Net charge to guest<x-help text="Charged before check-in minus the estimated refund -- the non-refundable total you actually keep (parking + early check-in if charged + late checkout + processing fee)." /></span>
                        <span class="text-lg font-bold text-slate-950 sm:text-right">${{ number_format($booking->netChargeCents() / 100, 2) }}</span>
                    </div>
                    <div class="flex flex-col gap-1 py-3 sm:col-span-2 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        <span class="flex items-center gap-2.5 font-bold text-slate-950"><x-icon name="refresh" class="h-4 w-4 shrink-0 text-emerald-600" />Estimated refund from incidentals hold<x-help text="Incidentals hold minus the deductions above, floored at $0. This is what to actually refund the guest after checkout -- doesn't move any money automatically." /></span>
                        <span class="text-lg font-extrabold text-slate-950 sm:text-right">${{ number_format($booking->estimatedIncidentalsRefund(), 2) }}</span>
                    </div>
                </dl>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {{-- Photo ID --}}
                <section class="card card-pad {{ $booking->parking_needed ? 'lg:col-span-3' : 'lg:col-span-5' }}">
                    <div class="flex items-center justify-between">
                        <h2 class="section-title">Photo ID</h2>
                        <span class="badge badge-active">{{ $booking->id_type === 'passport' ? 'Passport' : 'State-issued ID' }}</span>
                    </div>
                    @if($booking->id_scan_status)
                        @php
                            $idwScanBadge = match($booking->id_scan_status) {
                                'matched' => ['bg-emerald-50 border-emerald-200 text-emerald-800', 'Name matched'],
                                'expired' => ['bg-red-50 border-red-200 text-red-800', 'ID expired'],
                                'name_mismatch' => ['bg-red-50 border-red-200 text-red-800', 'Name mismatch'],
                                'manual_review' => ['bg-amber-50 border-amber-200 text-amber-800', 'Needs manual review'],
                                default => ['bg-slate-50 border-slate-200 text-slate-600', ucfirst(str_replace('_', ' ', $booking->id_scan_status))],
                            };
                        @endphp
                        <div class="mt-3 rounded-lg border p-3 text-sm {{ $idwScanBadge[0] }}">
                            <p class="font-semibold">ID scan: {{ $idwScanBadge[1] }}</p>
                            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                <dt class="font-medium opacity-75">Name on ID</dt>
                                <dd>{{ $booking->id_name ?: '— not read —' }}</dd>
                                <dt class="font-medium opacity-75">Typed name</dt>
                                <dd>{{ $booking->guest_name }}</dd>
                                <dt class="font-medium opacity-75">Date of birth</dt>
                                <dd>{{ $booking->id_date_of_birth?->format('M j, Y') ?? '— not read —' }}{{ $booking->id_age ? ' ('.$booking->id_age.' yrs)' : '' }}</dd>
                                <dt class="font-medium opacity-75">Expiry date</dt>
                                <dd>{{ $booking->id_expiry_date?->format('M j, Y') ?? '— not read —' }}</dd>
                                <dt class="font-medium opacity-75">Scanned</dt>
                                <dd>{{ $booking->id_scanned_at ? $booking->localTimestamp($booking->id_scanned_at)->format('M j, Y g:i A') : '—' }}</dd>
                            </dl>
                        </div>
                    @endif
                    @if($booking->photo_id_path || $booking->photo_id_back_path)
                        <div class="mt-4">
                            <div class="flex gap-4 overflow-x-auto border-b border-slate-200 text-sm font-semibold text-slate-500">
                                @if($booking->photo_id_path)
                                    <button type="button" id="photo-id-tab-front" onclick="switchPhotoIdTab('front')" class="-mb-px border-b-2 border-teal-700 pb-2 text-teal-800">Front</button>
                                @endif
                                @if($booking->photo_id_back_path)
                                    <button type="button" id="photo-id-tab-back" onclick="switchPhotoIdTab('back')" class="-mb-px pb-2 {{ $booking->photo_id_path ? '' : 'border-b-2 border-teal-700 text-teal-800' }}">Back</button>
                                @endif
                            </div>
                            @if($booking->photo_id_path)
                                <div id="photo-id-panel-front" class="mt-4">
                                    <button type="button" onclick="openPhotoIdModal('{{ route('admin.guests.photo-id-view', $booking) }}', 'Photo ID front')" class="block w-full text-left">
                                        <img src="{{ route('admin.guests.photo-id-view', $booking) }}" alt="Photo ID front" class="w-full max-h-64 rounded-lg border border-slate-200 object-contain bg-slate-50">
                                    </button>
                                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                                        <button type="button" onclick="openPhotoIdModal('{{ route('admin.guests.photo-id-view', $booking) }}', 'Photo ID front')" class="text-sm font-semibold text-teal-800">View full size</button>
                                        <a class="text-sm font-semibold text-teal-800" href="{{ route('admin.guests.photo-id', $booking) }}">Download original</a>
                                    </div>
                                    <div class="mt-4 border-t border-slate-100 pt-4">
                                        @if($booking->isFrontIdApproved())
                                            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800 font-semibold">Front approved {{ $booking->localTimestamp($booking->photo_id_front_approved_at)->format('M j, Y g:i A') }}</div>
                                        @elseif($booking->isCancelled())
                                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-sm text-slate-500">ID actions are disabled for a cancelled reservation.</div>
                                        @else
                                            <div class="flex gap-2">
                                                <form method="post" action="{{ route('admin.guests.id.approve', [$booking, 'front']) }}" class="flex-1">@csrf<button class="btn-primary w-full gap-2"><x-icon name="check" class="h-4 w-4" />Approve Front</button></form>
                                                <button type="button" class="btn-danger flex-1 gap-2" onclick="document.getElementById('decline-form-front-{{ $booking->id }}').classList.toggle('hidden')"><x-icon name="x" class="h-4 w-4" />Decline</button>
                                            </div>
                                            <form id="decline-form-front-{{ $booking->id }}" method="post" action="{{ route('admin.guests.id.decline', [$booking, 'front']) }}" class="hidden mt-2 grid gap-2">
                                                @csrf
                                                <textarea name="decline_reason" class="input" rows="3" placeholder="Reason for declining the front (shown to guest)" required>{{ old('decline_reason') }}</textarea>
                                                <button class="btn-secondary w-full">Submit Decline</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endif
                            @if($booking->photo_id_back_path)
                                <div id="photo-id-panel-back" class="mt-4 {{ $booking->photo_id_path ? 'hidden' : '' }}">
                                    <button type="button" onclick="openPhotoIdModal('{{ route('admin.guests.photo-id-back-view', $booking) }}', 'Photo ID back')" class="block w-full text-left">
                                        <img src="{{ route('admin.guests.photo-id-back-view', $booking) }}" alt="Photo ID back" class="w-full max-h-64 rounded-lg border border-slate-200 object-contain bg-slate-50">
                                    </button>
                                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                                        <button type="button" onclick="openPhotoIdModal('{{ route('admin.guests.photo-id-back-view', $booking) }}', 'Photo ID back')" class="text-sm font-semibold text-teal-800">View full size</button>
                                        <a class="text-sm font-semibold text-teal-800" href="{{ route('admin.guests.photo-id-back', $booking) }}">Download original</a>
                                    </div>
                                    <div class="mt-4 border-t border-slate-100 pt-4">
                                        @if($booking->isBackIdApproved())
                                            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800 font-semibold">Back approved {{ $booking->localTimestamp($booking->photo_id_back_approved_at)->format('M j, Y g:i A') }}</div>
                                        @elseif($booking->isCancelled())
                                            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-sm text-slate-500">ID actions are disabled for a cancelled reservation.</div>
                                        @else
                                            <div class="flex gap-2">
                                                <form method="post" action="{{ route('admin.guests.id.approve', [$booking, 'back']) }}" class="flex-1">@csrf<button class="btn-primary w-full gap-2"><x-icon name="check" class="h-4 w-4" />Approve Back</button></form>
                                                <button type="button" class="btn-danger flex-1 gap-2" onclick="document.getElementById('decline-form-back-{{ $booking->id }}').classList.toggle('hidden')"><x-icon name="x" class="h-4 w-4" />Decline</button>
                                            </div>
                                            <form id="decline-form-back-{{ $booking->id }}" method="post" action="{{ route('admin.guests.id.decline', [$booking, 'back']) }}" class="hidden mt-2 grid gap-2">
                                                @csrf
                                                <textarea name="decline_reason" class="input" rows="3" placeholder="Reason for declining the back (shown to guest)" required>{{ old('decline_reason') }}</textarea>
                                                <button class="btn-secondary w-full">Submit Decline</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="mt-4 font-semibold text-slate-950">Not uploaded</p>
                    @endif

                    @if($booking->isIdFullyApproved())
                        <div class="mt-5 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800 font-semibold">All ID photos approved{{ $booking->approved_at ? ' on '.$booking->localTimestamp($booking->approved_at)->format('M j, Y g:i A') : '' }}</div>
                    @endif
                </section>

                @if($booking->parking_needed)
                {{-- Vehicle / license plate photo, task 34 --}}
                <section class="card card-pad lg:col-span-2">
                    <h2 class="section-title">Vehicle</h2>
                    <p class="section-copy">Make/model and license plate photo, collected when the guest opted into parking.</p>
                    @if($booking->license_plate_photo_path)
                        <button type="button" onclick="openPhotoIdModal('{{ route('admin.guests.license-plate-view', $booking) }}', 'License plate')" class="mt-4 block w-full text-left">
                            <img src="{{ route('admin.guests.license-plate-view', $booking) }}" alt="License plate" class="w-full max-h-64 rounded-lg border border-slate-200 object-contain bg-slate-50">
                        </button>
                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-2">
                            <button type="button" onclick="openPhotoIdModal('{{ route('admin.guests.license-plate-view', $booking) }}', 'License plate')" class="text-sm font-semibold text-teal-800">View full size</button>
                            <a class="text-sm font-semibold text-teal-800" href="{{ route('admin.guests.license-plate', $booking) }}">Download original</a>
                        </div>
                    @else
                        <p class="mt-4 font-semibold text-slate-950">Not uploaded</p>
                    @endif
                </section>
                @endif
            </div>

            {{-- Communication --}}
            <section class="card card-pad">
                <div>
                    <div>
                        <h2 class="section-title">Communication</h2>
                        <p class="section-copy">Share the guest's secure link.</p>
                    </div>
                </div>

                <div class="mt-5">
                    <div class="grid gap-6">
                        <div id="guest-link-card" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-semibold text-slate-700">Secure guest URL</p>
                            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                                <input id="guest-url" readonly value="{{ $booking->publicUrl() }}" class="input mt-0 min-w-0 flex-1">
                                <button type="button" data-copy="#guest-url" class="btn-primary w-full justify-center gap-2 sm:w-auto"><x-icon name="copy" class="h-4 w-4" />Copy URL</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </div>

        {{-- Sidebar --}}
        <aside class="contents lg:grid lg:content-start lg:gap-3 lg:col-start-4 lg:row-start-1 lg:row-span-3 lg:sticky lg:top-20">
            <section class="card card-pad order-2 lg:order-none">
                <h2 class="section-title">Quick Actions</h2>
                <p class="section-copy">Take action on this booking.</p>


                @if($booking->isCancelled())
                    <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">This reservation was cancelled by the guest and is locked. All actions are disabled.</div>
                @else
                <div class="mt-5 grid gap-2.5">
                    {{-- Item 6: exactly 3, in order: Background Passed -> Deposit Verified -> Override GPS --}}
                    @if(! $booking->isApproved())
                        <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-sm text-slate-500">Waiting on Photo ID approval before {{ \App\Models\Setting::getValue('background_check_step_name', 'Background Check') }} can be marked.</div>
                    @elseif($booking->isBackgroundCheckComplete())
                        <div class="rounded-lg bg-indigo-50 border border-indigo-200 p-3 text-sm text-indigo-800 font-semibold">{{ \App\Models\Setting::getValue('background_check_step_name', 'Background Check') }} completed {{ $booking->localTimestamp($booking->background_check_completed_at)->format('M j, Y g:i A') }}</div>
                    @else
                        <form method="post" action="{{ route('admin.guests.background-check', $booking) }}">@csrf<button class="btn-secondary w-full gap-2"><x-icon name="shield-alert" class="h-4 w-4" />Background Passed</button></form>
                    @endif

                    @if($booking->isBackgroundCheckComplete())
                        @if($booking->isDepositVerified())
                            <div class="rounded-lg bg-teal-50 border border-teal-200 p-3 text-sm text-teal-800 font-semibold">Deposit verified {{ $booking->localTimestamp($booking->deposit_verified_at)->format('M j, Y g:i A') }}</div>
                        @else
                            <form method="post" action="{{ route('admin.guests.deposit-verified', $booking) }}" data-confirm-title="Mark Deposit Verified?" data-confirm="This will fully approve the guest. Before continuing, verify the guest has actually paid everything owed on / outside the platform: incidentals, parking, and early check-in (if applicable). This action does not check or record any of those payments itself.">@csrf<button class="btn-secondary w-full gap-2"><x-icon name="lock" class="h-4 w-4" />Deposit Verified</button></form>
                        @endif
                    @endif

                    @if($booking->isCheckinApproved())
                        <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800 font-semibold">Unit ready / check-in approved {{ $booking->localTimestamp($booking->checkin_approved_at)->format('M j, Y g:i A') }}</div>
                    @else
                        <form method="post" action="{{ route('admin.guests.approve-checkin', $booking) }}" data-confirm-title="Approve check-in?" data-confirm="Confirm the unit is ready and the guest may check in. This is what releases them from the 'unit isn't quite ready yet' screen and reveals their arrival details.">@csrf<button class="btn-primary w-full gap-2"><x-icon name="check-circle" class="h-4 w-4" />Unit Ready — Approve Check-In</button></form>
                    @endif

                    <form method="post" action="{{ route('admin.guests.override-gps', $booking) }}">@csrf<button class="btn-secondary w-full gap-2"><x-icon name="map" class="h-4 w-4" />Override GPS</button></form>

                    @if($booking->access_blocked_at)
                        <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-800">
                            <span class="font-semibold">Access blocked</span> since {{ $booking->localTimestamp($booking->access_blocked_at)->format('M j, Y g:i A') }}
                            <p class="mt-1">{{ $booking->access_blocked_reason }}</p>
                        </div>
                    @endif

                    {{-- Item 6: Manual Check-In/Out, Photo ID review, and Block Access move to a sub-menu --}}
                    <div class="relative mt-1">
                        <button type="button" onclick="document.getElementById('quick-actions-more-{{ $booking->id }}').classList.toggle('hidden')" class="btn-secondary w-full gap-2">
                            <x-icon name="more-vertical" class="h-4 w-4" />More actions
                        </button>
                        <div id="quick-actions-more-{{ $booking->id }}" class="hidden mt-2 grid gap-2.5 rounded-lg border border-slate-200 p-3">
                            <form method="post" action="{{ route('admin.guests.override', $booking) }}">@csrf<button class="btn-secondary w-full gap-2"><x-icon name="contact-guest-services" class="h-4 w-4" />Manually Mark Checked In</button></form>
                            <form method="post" action="{{ route('admin.guests.override-checkout', $booking) }}">@csrf<button class="btn-secondary w-full gap-2"><x-icon name="contact-guest-services" class="h-4 w-4" />Manually Mark Checked Out</button></form>
                            <form method="post" action="{{ route('admin.guests.mark-id', $booking) }}">@csrf<button class="btn-secondary w-full gap-2"><x-icon name="upload" class="h-4 w-4" />Mark Photo ID Received</button></form>
                            <form method="post" action="{{ route('admin.guests.bypass-vehicle-info', $booking) }}">@csrf<button class="btn-secondary w-full gap-2"><x-icon name="car" class="h-4 w-4" />Bypass Vehicle Info</button></form>

                            @if($booking->access_blocked_at)
                                <form method="post" action="{{ route('admin.guests.unblock-access', $booking) }}">@csrf<button class="btn-secondary w-full gap-2"><x-icon name="unlock" class="h-4 w-4" />Restore Access</button></form>
                            @else
                                <button type="button" class="btn-danger w-full gap-2" onclick="document.getElementById('block-form-{{ $booking->id }}').classList.toggle('hidden')">
                                    <x-icon name="alert-triangle" class="h-4 w-4" />Block Access
                                </button>
                                <form id="block-form-{{ $booking->id }}" method="post" action="{{ route('admin.guests.block-access', $booking) }}" class="hidden grid gap-2">
                                    @csrf
                                    <textarea name="access_blocked_reason" class="input" rows="3" placeholder="Reason (shown to guest)" required>{{ old('access_blocked_reason') }}</textarea>
                                    <button class="btn-secondary w-full">Submit Block</button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </section>

            <section class="card card-pad order-4 lg:order-none">
                <h2 class="section-title">Status Overview</h2>
                @php
                    $steps = [
                        'Email Received' => filled($booking->email),
                        'Photo ID Uploaded' => filled($booking->photo_id_path),
                        'Photo ID Approval' => $booking->isApproved(),
                        \App\Models\Setting::getValue('background_check_step_name', 'Background Check') => $booking->isBackgroundCheckComplete(),
                        'Deposit Verified' => $booking->isDepositVerified(),
                        'Unit Ready / Check-In Approved' => $booking->isCheckinApproved(),
                        'GPS Verified' => $booking->gps_verified,
                        'Currently Hosting' => $booking->isCheckedIn(),
                        'Checked Out' => filled($booking->checked_out_at),
                    ];
                    $progress = round((count(array_filter($steps)) / count($steps)) * 100);
                @endphp
                <p class="section-copy">Overall progress</p>
                <div class="mt-2 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width: {{ $progress }}%"></div></div>
                    <span class="text-sm font-semibold text-slate-700">{{ $progress }}%</span>
                </div>
                <div class="mt-5 grid gap-1 text-sm">
                    @foreach($steps as $label => $done)
                        <div class="flex items-center justify-between border-b border-slate-100 py-2.5 last:border-0"><span class="text-slate-700">{{ $label }}</span><span class="badge {{ $done ? 'badge-active' : 'badge-pending' }}">{{ $done ? 'Done' : 'Pending' }}</span></div>
                    @endforeach
                </div>
            </section>

            <section class="card card-pad order-5 lg:order-none">
                <h2 class="section-title">Preview Guest Flow</h2>
                <p class="section-copy">Open any guest state without changing the real status.</p>
                <div class="mt-4 grid gap-2">
                    @foreach(['identity' => 'Pre Check-In', 'waiting' => 'Waiting', 'arrival' => 'Check-In Day', 'guide' => 'Welcome Guide', 'checkout' => 'Checkout Day'] as $state => $label)
                        <a class="btn-secondary justify-start" href="{{ route('admin.guests.preview', [$booking, $state]) }}" target="_blank">{{ $label }}</a>
                    @endforeach
                </div>
            </section>
        </aside>
    </div>

    {{-- Guest progress timeline (always last, full width) --}}
    <section class="mt-6 card card-pad">
        <h2 class="section-title">Guest Progress Timeline</h2>
        @php
            $timelineSteps = [
                ['properties', 'Guest Created', $booking->created_at, true],
                ['mail', 'Email Submitted', $booking->updated_at, filled($booking->email)],
                ['upload', 'Photo ID Uploaded', $booking->updated_at, filled($booking->photo_id_path)],
                ['security', 'Photo ID Approval', $booking->approved_at, $booking->isApproved()],
                ['shield-alert', \App\Models\Setting::getValue('background_check_step_name', 'Background Check'), $booking->background_check_completed_at, $booking->isBackgroundCheckComplete()],
                ['lock', 'Deposit Verified', $booking->deposit_verified_at, $booking->isDepositVerified()],
                ['map', 'GPS Verified', $booking->checked_in_at, $booking->gps_verified],
                ['contact-guest-services', 'Currently Hosting', $booking->checked_in_at, $booking->isCheckedIn()],
                ['contact-guest-services', 'Checked Out', $booking->checked_out_at, filled($booking->checked_out_at)],
            ];
        @endphp
        <div class="mt-8 flex items-start overflow-x-auto pb-2">
            @foreach($timelineSteps as $i => [$icon, $label, $time, $done])
                @if($i > 0)
                    <div class="mt-7 h-px w-10 flex-shrink-0 sm:w-16 {{ $done ? 'bg-emerald-400' : 'border-t-2 border-dashed border-amber-300 bg-transparent' }}"></div>
                @endif
                <div class="flex w-28 flex-shrink-0 flex-col items-center text-center sm:w-32">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full border-2 {{ $done ? 'border-emerald-400 bg-emerald-50 text-emerald-600' : 'border-amber-300 bg-amber-50 text-amber-500' }}">
                        <x-icon :name="$icon" class="h-5 w-5" />
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-950">{{ $label }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $done ? ($time ? $booking->localTimestamp($time)->format('M j, Y g:i A') : 'Completed') : 'Pending' }}</p>
                    <span class="mt-2 badge {{ $done ? 'badge-active' : 'badge-pending' }}">{{ $done ? 'Done' : 'Open' }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Media Picker Modal (for editor image insert) --}}
    <div id="media-picker-modal" class="fixed inset-0 hidden items-center justify-center bg-slate-950/40 p-4" style="z-index:2147483000;">
        <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p id="media-picker-breadcrumb" class="text-sm font-bold text-slate-700">Library</p>
                <div class="flex items-center gap-2">
                    <label class="btn-secondary cursor-pointer text-xs">
                        Upload
                        <input type="file" id="media-picker-upload-input" accept="image/*" class="sr-only">
                    </label>
                    <button type="button" onclick="closeMediaPickerForEditor()" class="text-slate-400 hover:text-slate-700">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>
            </div>
            <div id="media-picker-body" class="grid max-h-96 grid-cols-3 gap-3 overflow-y-auto sm:grid-cols-4"></div>
        </div>
    </div>

    {{-- Photo ID Viewer Modal --}}
    <div id="photo-id-modal" tabindex="-1" class="fixed inset-0 hidden items-center justify-center bg-slate-950/40 p-4" style="z-index:2147483000;">
        <div class="w-full max-w-2xl rounded-xl bg-white p-5 shadow-xl">
            <div class="mb-3 flex items-center justify-between gap-3">
                <p id="photo-id-modal-title" class="text-sm font-bold text-slate-700">Photo ID</p>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="photoIdZoomOut()" class="rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-50">&minus;</button>
                    <span id="photo-id-modal-zoom-level" class="w-10 text-center text-xs font-semibold text-slate-500">100%</span>
                    <button type="button" onclick="photoIdZoomIn()" class="rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-50">+</button>
                    <button type="button" onclick="photoIdZoomReset()" class="rounded-md border border-slate-200 px-2 py-1 text-xs font-semibold text-slate-500 hover:bg-slate-50">Reset</button>
                    <button type="button" onclick="closePhotoIdModal()" class="text-slate-400 hover:text-slate-700">
                        <x-icon name="x" class="h-5 w-5" />
                    </button>
                </div>
            </div>
            <div id="photo-id-modal-viewport" class="max-h-[75vh] w-full overflow-hidden rounded-lg bg-slate-100" style="cursor: grab;">
                <img id="photo-id-modal-img" src="" alt="" class="h-full w-full select-none object-contain" style="transform-origin: center center; transition: transform 0.08s ease-out; user-select:none; -webkit-user-drag:none;" draggable="false">
            </div>

            @if(($booking->photo_id_path || $booking->photo_id_back_path) && !$booking->isApproved() && ! $booking->isCancelled())
                <div class="mt-4 flex gap-2 border-t border-slate-100 pt-4">
                    <form method="post" action="{{ route('admin.guests.approve', $booking) }}" class="flex-1">@csrf<button class="btn-primary w-full gap-2"><x-icon name="check" class="h-4 w-4" />Approve</button></form>
                    <button type="button" class="btn-danger flex-1 gap-2" onclick="document.getElementById('decline-form-{{ $booking->id }}').classList.toggle('hidden'); closePhotoIdModal();"><x-icon name="x" class="h-4 w-4" />Decline</button>
                </div>
            @endif
        </div>
    </div>

    {{-- Force TinyMCE's floating menus/dropdowns/overflow drawer below our modal --}}
    <style>
    .tox-tinymce-aux,
    .tox.tox-silver-sink,
    .tox-dialog-wrap {
        z-index: 1000 !important;
    }
    .tox-menu.tox-collection.tox-collection--list {
        max-height: 320px !important;
        overflow-y: auto !important;
    }
    .tox.tox-tinymce.tox-fullscreen,
    body.tox-fullscreen-body .tox.tox-tinymce.tox-fullscreen {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        z-index: 2147483001 !important;
    }
    </style>

    {{-- Google Fonts loaded on the PAGE so toolbar dropdown labels render correctly --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Merriweather:wght@400;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">

    <script>
    let __mediaPickerCurrentFolder = null;

    let __photoIdZoom = 1;
    let __photoIdPanX = 0;
    let __photoIdPanY = 0;
    let __photoIdDragging = false;
    let __photoIdDragStartX = 0;
    let __photoIdDragStartY = 0;
    const PHOTO_ID_ZOOM_MIN = 1;
    const PHOTO_ID_ZOOM_MAX = 4;
    const PHOTO_ID_ZOOM_STEP = 0.25;

    function __photoIdApplyTransform() {
        const img = document.getElementById('photo-id-modal-img');
        img.style.transform = `translate(${__photoIdPanX}px, ${__photoIdPanY}px) scale(${__photoIdZoom})`;
        document.getElementById('photo-id-modal-zoom-level').textContent = Math.round(__photoIdZoom * 100) + '%';
        const viewport = document.getElementById('photo-id-modal-viewport');
        viewport.style.cursor = __photoIdZoom > 1 ? 'grab' : 'default';
    }

    function __photoIdClampPan() {
        if (__photoIdZoom <= 1) {
            __photoIdPanX = 0;
            __photoIdPanY = 0;
            return;
        }
        const viewport = document.getElementById('photo-id-modal-viewport');
        const maxPanX = (viewport.clientWidth * (__photoIdZoom - 1)) / 2;
        const maxPanY = (viewport.clientHeight * (__photoIdZoom - 1)) / 2;
        __photoIdPanX = Math.max(-maxPanX, Math.min(maxPanX, __photoIdPanX));
        __photoIdPanY = Math.max(-maxPanY, Math.min(maxPanY, __photoIdPanY));
    }

    function photoIdZoomIn() {
        __photoIdZoom = Math.min(PHOTO_ID_ZOOM_MAX, __photoIdZoom + PHOTO_ID_ZOOM_STEP);
        __photoIdClampPan();
        __photoIdApplyTransform();
    }

    function photoIdZoomOut() {
        __photoIdZoom = Math.max(PHOTO_ID_ZOOM_MIN, __photoIdZoom - PHOTO_ID_ZOOM_STEP);
        __photoIdClampPan();
        __photoIdApplyTransform();
    }

    function photoIdZoomReset() {
        __photoIdZoom = 1;
        __photoIdPanX = 0;
        __photoIdPanY = 0;
        __photoIdApplyTransform();
    }

    function openPhotoIdModal(url, title) {
        document.getElementById('photo-id-modal-img').src = url;
        document.getElementById('photo-id-modal-title').textContent = title;
        const modal = document.getElementById('photo-id-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        modal.focus();
        modal.scrollIntoView({ behavior: 'instant', block: 'center' });
        photoIdZoomReset();
    }

    function closePhotoIdModal() {
        const modal = document.getElementById('photo-id-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.getElementById('photo-id-modal-img').src = '';
        document.body.style.overflow = '';
        photoIdZoomReset();
    }

    (function initPhotoIdZoomInteractions() {
        const viewport = document.getElementById('photo-id-modal-viewport');
        const img = document.getElementById('photo-id-modal-img');

        viewport.addEventListener('wheel', function (e) {
            if (document.getElementById('photo-id-modal').classList.contains('hidden')) return;
            e.preventDefault();
            if (e.deltaY < 0) {
                __photoIdZoom = Math.min(PHOTO_ID_ZOOM_MAX, __photoIdZoom + PHOTO_ID_ZOOM_STEP);
            } else {
                __photoIdZoom = Math.max(PHOTO_ID_ZOOM_MIN, __photoIdZoom - PHOTO_ID_ZOOM_STEP);
            }
            __photoIdClampPan();
            __photoIdApplyTransform();
        }, { passive: false });

        viewport.addEventListener('mousedown', function (e) {
            if (__photoIdZoom <= 1) return;
            __photoIdDragging = true;
            __photoIdDragStartX = e.clientX - __photoIdPanX;
            __photoIdDragStartY = e.clientY - __photoIdPanY;
            viewport.style.cursor = 'grabbing';
        });

        window.addEventListener('mousemove', function (e) {
            if (!__photoIdDragging) return;
            __photoIdPanX = e.clientX - __photoIdDragStartX;
            __photoIdPanY = e.clientY - __photoIdDragStartY;
            __photoIdClampPan();
            __photoIdApplyTransform();
        });

        window.addEventListener('mouseup', function () {
            if (!__photoIdDragging) return;
            __photoIdDragging = false;
            viewport.style.cursor = __photoIdZoom > 1 ? 'grab' : 'default';
        });

        img.addEventListener('dblclick', function () {
            if (__photoIdZoom > 1) {
                photoIdZoomReset();
            } else {
                __photoIdZoom = 2;
                __photoIdApplyTransform();
            }
        });
    })();

    
    function switchPhotoIdTab(side) {
        const frontPanel = document.getElementById('photo-id-panel-front');
        const backPanel = document.getElementById('photo-id-panel-back');
        const frontTab = document.getElementById('photo-id-tab-front');
        const backTab = document.getElementById('photo-id-tab-back');
        const activeClasses = ['border-b-2', 'border-teal-700', 'text-teal-800'];

        if (side === 'front') {
            if (frontPanel) frontPanel.classList.remove('hidden');
            if (backPanel) backPanel.classList.add('hidden');
            if (frontTab) frontTab.classList.add(...activeClasses);
            if (backTab) backTab.classList.remove(...activeClasses);
        } else {
            if (backPanel) backPanel.classList.remove('hidden');
            if (frontPanel) frontPanel.classList.add('hidden');
            if (backTab) backTab.classList.add(...activeClasses);
            if (frontTab) frontTab.classList.remove(...activeClasses);
        }
    }

    function closeMediaPickerForEditor() {
        const modal = document.getElementById('media-picker-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openMediaPickerForEditor() {
        const modal = document.getElementById('media-picker-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        loadMediaPickerForEditor(null);
    }

    function loadMediaPickerForEditor(folderId) {
        __mediaPickerCurrentFolder = folderId;
        const url = '{{ route("admin.media.picker") }}' + (folderId ? '?folder_id=' + folderId : '');
        fetch(url).then(r => r.json()).then(data => {
            const body = document.getElementById('media-picker-body');
            const crumbText = data.breadcrumb.length ? data.breadcrumb.map(c => c.name).join(' / ') : 'Library';
            document.getElementById('media-picker-breadcrumb').textContent = crumbText;
            body.innerHTML = '';
            if (folderId !== null) {
                const up = document.createElement('button');
                up.type = 'button';
                up.className = 'col-span-full text-left text-xs font-semibold text-blue-600 hover:underline';
                up.textContent = 'Back';
                const parentId = data.breadcrumb.length > 1 ? data.breadcrumb[data.breadcrumb.length - 2].id : null;
                up.onclick = () => loadMediaPickerForEditor(parentId);
                body.appendChild(up);
            }
            data.folders.forEach(folder => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'flex flex-col items-center gap-1 rounded-lg border border-slate-200 p-3 hover:bg-slate-50';
                el.innerHTML = '<span class="text-xs font-semibold">' + folder.name + '</span>';
                el.onclick = () => loadMediaPickerForEditor(folder.id);
                body.appendChild(el);
            });
            data.files.forEach(file => {
                const el = document.createElement('button');
                el.type = 'button';
                el.className = 'overflow-hidden rounded-lg border border-slate-200 bg-slate-50 hover:ring-2 hover:ring-blue-400';
                el.innerHTML = '<img src="' + file.url + '" class="h-20 w-full object-contain p-1">';
                el.onclick = () => {
                    if (window.tinymce && tinymce.activeEditor) {
                        tinymce.activeEditor.insertContent('<img src="' + file.url + '" alt="' + (file.name || '') + '" style="max-width:100%;">');
                    }
                    closeMediaPickerForEditor();
                };
                body.appendChild(el);
            });
            if (!data.folders.length && !data.files.length) {
                body.innerHTML += '<p class="col-span-full text-center text-sm text-slate-400">No images in this folder yet.</p>';
            }
        });
    }

    document.getElementById('media-picker-upload-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('image', file);
        if (__mediaPickerCurrentFolder) formData.append('media_folder_id', __mediaPickerCurrentFolder);
        fetch('{{ route("admin.media.files.store") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: formData,
        }).then(() => {
            loadMediaPickerForEditor(__mediaPickerCurrentFolder);
            e.target.value = '';
        });
    });
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js"></script>
    <script>
    tinymce.init({
        relative_urls: false,
        remove_script_host: false,
        selector: '#welcome-message-editor',
        plugins: 'lists advlist link code table searchreplace wordcount visualblocks charmap emoticons preview anchor fullscreen nonbreaking',
        toolbar: 'undo redo | bold italic underline forecolor backcolor | alignleft aligncenter alignright | bullist numlist | customlineheight | link insertimage table anchor charmap emoticons | searchreplace preview fullscreen | removeformat code | fontfamily fontsize',
        browser_spellcheck: true,
        contextmenu: false,
        font_size_formats: '8px 10px 12px 14px 16px 18px 20px 24px 28px 32px 36px 42px 48px 60px 72px',
        font_family_formats:
            'Arial=arial,helvetica,sans-serif;' +
            'Helvetica=helvetica,arial,sans-serif;' +
            'Times New Roman=times new roman,times,serif;' +
            'Georgia=georgia,palatino,serif;' +
            'Garamond=garamond,serif;' +
            'Verdana=verdana,geneva,sans-serif;' +
            'Tahoma=tahoma,arial,helvetica,sans-serif;' +
            'Trebuchet MS=trebuchet ms,geneva,sans-serif;' +
            'Courier New=courier new,courier,monospace;' +
            'Comic Sans MS=comic sans ms,sans-serif;' +
            'Impact=impact,sans-serif;' +
            'Lucida Sans=lucida sans unicode,lucida grande,sans-serif;' +
            'Roboto=Roboto,arial,sans-serif;' +
            'Open Sans=\'Open Sans\',arial,sans-serif;' +
            'Montserrat=Montserrat,arial,sans-serif;' +
            'Merriweather=Merriweather,georgia,serif;' +
            'Playfair Display=\'Playfair Display\',georgia,serif',
        color_map: [
            '000000','Black', '424242','Dark Gray', '757575','Gray', 'BDBDBD','Light Gray', 'FFFFFF','White',
            'B71C1C','Dark Red', 'E53935','Red', 'F44336','Bright Red', 'FF7043','Orange Red', 'FB8C00','Orange',
            'FDD835','Yellow', 'C0CA33','Olive', '7CB342','Light Green', '43A047','Green', '00897B','Teal',
            '00ACC1','Cyan', '1E88E5','Blue', '3949AB','Indigo', '5E35B1','Purple', '8E24AA','Magenta', 'D81B60','Pink'
        ],
        valid_styles: {
            '*': 'font-size,font-family,color,background-color,text-align,text-decoration,line-height'
        },
        menubar: false,
        toolbar_mode: 'wrap',
        height: 320,
        ui_mode: 'split',
        promotion: false,
        branding: false,
        content_css: false,
        content_style: `
            @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&family=Open+Sans:wght@400;700&family=Montserrat:wght@400;700&family=Merriweather:wght@400;700&family=Playfair+Display:wght@400;700&display=swap');
            body { font-family: Helvetica, Arial, sans-serif; font-size: 14px; }
            p { margin: 0; }
        `,
        setup: function(editor) {
            var lineHeightValues = ['0.3', '0.5', '0.7', '0.9', '1', '1.15', '1.3', '1.5', '1.75', '2', '2.5', '3', '3.5', '4', '4.5', '5'];
            var lastSelectionRange = null;
            editor.on('NodeChange KeyUp MouseUp', function() {
                try { lastSelectionRange = editor.selection.getRng().cloneRange(); } catch (e) {}
            });
            editor.ui.registry.addMenuButton('customlineheight', {
                icon: 'line-height',
                tooltip: 'Line height',
                fetch: function(callback) {
                    var currentValue = null;
                    try {
                        var node0 = editor.selection.getNode();
                        var block0 = editor.dom.getParent(node0, editor.dom.isBlock) || node0;
                        if (block0 && block0.nodeName !== 'BODY') {
                            currentValue = editor.dom.getStyle(block0, 'line-height') || null;
                        }
                    } catch (e) {}
                    var items = lineHeightValues.map(function(v) {
                        return {
                            type: 'togglemenuitem',
                            text: v,
                            active: currentValue === v,
                            onAction: function() {
                                editor.focus();
                                if (lastSelectionRange) {
                                    try { editor.selection.setRng(lastSelectionRange); } catch (e) {}
                                }
                                var blocks = editor.selection.getSelectedBlocks();
                                if (!blocks || !blocks.length) {
                                    var node = editor.selection.getNode();
                                    var single = editor.dom.getParent(node, editor.dom.isBlock) || node;
                                    blocks = [single];
                                }
                                var applied = 0;
                                blocks.forEach(function(block) {
                                    if (block && block.nodeName !== 'BODY') {
                                        editor.dom.setStyle(block, 'line-height', v);
                                        applied++;
                                    }
                                });
                                if (applied > 0) {
                                    editor.nodeChanged();
                                }
                            }
                        };
                    });
                    callback(items);
                }
            });
            editor.ui.registry.addButton('insertimage', {
                icon: 'image',
                tooltip: 'Insert image from library',
                onAction: function() {
                    openMediaPickerForEditor();
                }
            });
        }
    });
    </script>

    <script>
    function toggleGuestDetailsEdit(btn) {
        var panel = document.getElementById('guest-details-edit-panel');
        if (!panel) return;
        var willShow = panel.classList.contains('hidden');
        panel.classList.toggle('hidden');
        if (btn) btn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        if (willShow) panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Per-field inline editing for the Guest Details billing rows: the
    // field is always present in the DOM (locked via pointer-events +
    // tabindex, not the "disabled" attribute, so its current value still
    // submits along with whichever field you actually edited). Clicking a
    // field's pencil unlocks just that field and turns the pencil itself
    // into the save/submit action -- the card never expands or splits.
    function unlockLedgerField(id) {
        var el = document.getElementById(id);
        var btn = document.getElementById(id + '-btn');
        if (!el || !btn) return;
        var isLocked = el.classList.contains('pointer-events-none');
        if (isLocked) {
            el.classList.remove('pointer-events-none');
            el.removeAttribute('tabindex');
            el.classList.add('input');
            el.classList.remove('border-none', 'bg-transparent', 'p-0', 'appearance-none');
            el.focus();
            if (el.select) el.select();
            var pencil = btn.querySelector('.lf-pencil');
            var check = btn.querySelector('.lf-check');
            if (pencil) pencil.classList.add('hidden');
            if (check) check.classList.remove('hidden');
        } else {
            // Field is already unlocked -- this click means "save". Submit
            // the form explicitly via JS rather than mutating this
            // button's type to "submit", which (in most browsers) would
            // trigger a submit on the very same click that set it,
            // reloading the page before anything could be typed.
            var form = el.closest('form');
            if (form) {
                if (form.requestSubmit) form.requestSubmit();
                else form.submit();
            }
        }
    }

    (function () {
        var phoneInput = document.getElementById('guest-detail-phone-input');
        if (!phoneInput) return;
        phoneInput.addEventListener('input', function (e) {
            var digits = e.target.value.replace(/\D/g, '').slice(0, 10);
            var formatted = digits;
            if (digits.length > 6) {
                formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
            } else if (digits.length > 3) {
                formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
            } else if (digits.length > 0) {
                formatted = '(' + digits;
            }
            e.target.value = formatted;
        });
    })();
    </script>
    <script src="{{ asset('js/guest-phone-country.js') }}"></script>
</x-admin-layout>
