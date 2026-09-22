<x-admin-layout :title="$booking->exists ? 'Edit Guest' : 'Add Guest'">
    <div class="page-header">
        <div>
            <p class="eyebrow">Guest details</p>
            <h1 class="page-title">{{ $booking->exists ? 'Edit guest' : 'Add guest' }}</h1>
            <p class="page-subtitle">Create a secure guest URL from guest details. Guests use it for ID upload, GPS arrival, and the welcome guide.</p>
        </div>
        <a href="{{ route('admin.guests.index') }}" class="btn-secondary">Back to Guests</a>
    </div>

    <form method="post" action="{{ $booking->exists ? route('admin.guests.update', $booking) : route('admin.guests.store') }}" class="grid gap-6 xl:grid-cols-[1fr_360px]">
        @csrf @if($booking->exists) @method('put') @endif
        <section class="card card-pad">
            <h2 class="section-title">Guest and stay details</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <label class="field-label">Reservation ID (Airbnb/VRBO) <span class="text-red-600">*</span><input name="reservation_id" value="{{ old('reservation_id', $booking->reservation_id) }}" placeholder="Required, from Airbnb/VRBO" required class="input"></label>
                <label class="field-label">Booking platform<input name="booking_platform" list="booking-platform-options" value="{{ old('booking_platform', $booking->booking_platform) }}" placeholder="Airbnb, Vrbo, Booking.com…" class="input"><datalist id="booking-platform-options"><option value="Airbnb"></option><option value="Vrbo"></option><option value="Booking.com"></option><option value="Expedia"></option><option value="Direct"></option></datalist><span class="field-help">Shown to the guest on the payment screen ("Pay on …"). Filled automatically for channel-manager bookings.</span></label>
                <label class="field-label">Guest name <span class="text-red-600">*</span><input name="guest_name" value="{{ old('guest_name', $booking->guest_name) }}" required placeholder="Jordan Taylor" class="input"></label>
                @if($booking->exists)
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
                        <input id="guest-phone-input" name="phone" value="{{ old('phone', \App\Support\PhoneFormatter::format($booking->phone)) }}" placeholder="(555) 555-0199" maxlength="18" class="input min-w-0 flex-1">
                    </div>
                </div>
                <label class="field-label">Email<input name="email" value="{{ old('email', $booking->email) }}" placeholder="guest@example.com" class="input"></label>
                @endif
                <label class="field-label">Check-in <span class="text-red-600">*</span><input type="date" name="check_in_date" value="{{ old('check_in_date', optional($booking->check_in_date)->format('Y-m-d')) }}" required class="input"></label>
                <label class="field-label">Check-out <span class="text-red-600">*</span><input type="date" name="check_out_date" value="{{ old('check_out_date', optional($booking->check_out_date)->format('Y-m-d')) }}" required class="input"></label>
                <label class="field-label md:col-span-2">Property <span class="text-red-600">*</span><select name="property_id" required class="input"><option value="" disabled @selected(!old('property_id', $booking->property_id))>Select a property...</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected(old('property_id', $booking->property_id)==$property->id)>{{ $property->name }}</option>@endforeach</select></label>
                <label class="field-label">ID type <span class="text-red-600">*</span><select name="id_type" required class="input"><option value="state_id" @selected(old('id_type', $booking->id_type ?: 'state_id')==='state_id')>State-issued ID (US guest)</option><option value="passport" @selected(old('id_type', $booking->id_type ?: 'state_id')==='passport')>Passport (international guest)</option></select></label>
            </div>
        </section>

        <aside class="card card-pad">
            <h2 class="section-title">Status controls</h2>
            <p class="section-copy">Use these to reflect what has happened outside the public guest flow.</p>
@if($booking->exists)
            <label class="field-label mt-5">Parking<select name="parking_needed" class="input"><option value="">Unknown</option><option value="1" @selected(old('parking_needed', $booking->parking_needed)==='1' || old('parking_needed', $booking->parking_needed)===true)>Yes, guest needs parking</option><option value="0" @selected(old('parking_needed', $booking->parking_needed)==='0' || old('parking_needed', $booking->parking_needed)===false)>No parking needed</option></select></label>
            @if($booking->parking_needed)
            <div class="field-label mt-5">
                <span>Parking charge</span>
                <p class="field-help mt-1">${{ number_format($booking->effectiveParkingCharge() ?? 0, 2) }} — auto-calculated from the property's weekday rates.</p>
                <span class="field-help">Edit the amount held/charged for this specific guest from the guest's page, not here.</span>
            </div>
            @endif
            <div class="field-label mt-5">
                <span>Incidentals hold</span>
                <p class="field-help mt-1">${{ number_format($booking->effectiveIncidentalsCharge() ?? 0, 2) }}</p>
                <span class="field-help">Editing per-guest amounts is moving to a single guest page — coming in the next update.</span>
            </div>
            @endif
@if($booking->exists)
            <label class="field-label mt-5">
                Early check-in billing window (admin only)
                <select name="early_checkin_tier" class="input">
                    <option value="">None</option>
                    <option value="8am_12pm" @selected(old('early_checkin_tier', $booking->early_checkin_tier) === '8am_12pm' || old('early_checkin_tier', $booking->early_checkin_tier) === '8am')>8:00 AM - 12:00 PM</option>
                    <option value="12pm_2pm" @selected(old('early_checkin_tier', $booking->early_checkin_tier) === '12pm_2pm' || old('early_checkin_tier', $booking->early_checkin_tier) === '12pm')>12:00 PM - 2:00 PM</option>
                    <option value="2pm_4pm" @selected(old('early_checkin_tier', $booking->early_checkin_tier) === '2pm_4pm')>2:00 PM - 4:00 PM</option>
                </select>
                @if($booking->early_checkin_tier)
                    <span class="field-help">Charge: ${{ number_format($booking->earlyCheckinCharge() ?? 0, 2) }} (from the property's rate for this window).</span>
                @else
                    <span class="field-help">Set this if the early check-in should be billed.</span>
                @endif
            </label>
            <div class="field-label mt-5">
                <span>Late checkout billing (admin only)</span>
                <select name="late_checkout_type" class="input mt-1">
                    <option value="">Not applicable</option>
                    <option value="authorized" @selected(old('late_checkout_type', $booking->late_checkout_type)==='authorized')>Authorized</option>
                    <option value="unauthorized" @selected(old('late_checkout_type', $booking->late_checkout_type)==='unauthorized')>Unauthorized</option>
                </select>
                <label class="field-label mt-3">Hours late (authorized only)<input type="number" step="0.25" min="0" name="late_checkout_hours" value="{{ old('late_checkout_hours', $booking->late_checkout_hours) }}" placeholder="e.g. 2" class="input"></label>
                <label class="field-label mt-3">Actual checkout time (unauthorized only)<input type="datetime-local" name="late_checkout_actual_time" value="{{ old('late_checkout_actual_time', optional($booking->localTimestamp($booking->late_checkout_actual_time))->format('Y-m-d\TH:i')) }}" class="input"></label>
                <span class="field-help">Separate from the system's automatic checkout timestamp; enter what time the guest actually left for an unauthorized late checkout, so hours can be calculated.</span>
                @if($booking->late_checkout_type)
                    <span class="field-help font-semibold">Charge: ${{ number_format($booking->lateCheckoutCharge() ?? 0, 2) }}</span>
                @endif
            </div>
            @endif
            <label class="field-label mt-5 flex items-center gap-2">
                <input type="checkbox" name="photo_id_received" value="1" @checked(old('photo_id_received', $booking->photo_id_received))>
                <span>Photo ID Already Received</span>
            </label>
            <p class="field-help">If enabled, the guest will not be asked to upload a photo ID during check-in.</p>
@if($booking->exists)
            <label class="field-label mt-5">Requested Check-in Time<input type="time" name="checkin_time_preference" value="{{ old('checkin_time_preference', $booking->checkin_time_preference) }}" class="input"></label>
            <label class="field-label mt-5">Requested Check-out Time<input type="time" name="checkout_time_preference" value="{{ old('checkout_time_preference', $booking->checkout_time_preference) }}" class="input"></label>
            @endif
            <label class="field-label mt-5">Status<select name="status" class="input">@foreach(['pending','pre_checkin_complete','awaiting_deposit','guest_approved','currently_hosting','checked_out'] as $status)<option value="{{ $status }}" @selected(old('status', $booking->status ?: 'pending')===$status)>{{ str($status)->replace('_',' ')->title() }}</option>@endforeach</select></label>
            <button class="btn-primary mt-6 w-full">Save guest</button>
            @if($booking->exists)
                <a href="{{ route('admin.guests.show', $booking) }}" class="btn-secondary mt-3 w-full">View guest URL</a>
            @endif
        </aside>

        @if($instructionSteps->isNotEmpty())
        @endif
        <section class="card card-pad xl:col-span-2">
            <h2 class="section-title">Internal notes</h2>
            <p class="section-copy">Notes are visible to admins only and never shown on the guest page.</p>
            <label class="field-label mt-5">Notes<textarea name="notes" rows="5" placeholder="Arrival requests, internal reminders, owner notes..." class="textarea">{{ old('notes', $booking->notes) }}</textarea></label>
        </section>
    </form>

    <script>
        (function () {
            var phoneInput = document.getElementById('guest-phone-input');
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