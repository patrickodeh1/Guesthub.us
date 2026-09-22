<x-admin-layout :title="$property->exists ? 'Edit Property' : 'Add Property'">
    <div class="page-header">
        <div>
            <p class="eyebrow">Property setup</p>
            <h1 class="page-title">{{ $property->exists ? 'Edit property' : 'Add property' }}</h1>
            <p class="page-subtitle">Configure the public guest page, GPS arrival point, maps, images, and all arrival instructions for this unit.</p>
        </div>
        <a href="{{ $returnTo ?? route('admin.properties.index') }}" class="btn-secondary">Back</a>
    </div>

    <form method="post" enctype="multipart/form-data" action="{{ $property->exists ? route('admin.properties.update', $property) : route('admin.properties.store') }}" class="grid items-start gap-6 xl:grid-cols-[1fr_360px]">
        @csrf @if($property->exists) @method('put') @endif
        <input type="hidden" name="return_to" value="{{ $returnTo ?? '' }}">

        <div class="grid content-start gap-6">
        <section class="card card-pad">
            <h2 class="section-title">Property details</h2>
            <p class="section-copy">This information appears throughout the guest welcome experience.</p>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <label class="field-label">Name <span class="text-red-600">*</span><input name="name" value="{{ old('name', $property->name) }}" required placeholder="Lumina Hotel & Residences" class="input">@error('name')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                <label class="field-label">Slug<input name="slug" value="{{ old('slug', $property->slug) }}" placeholder="Auto-generated from name" class="input"><span class="field-help">Used in admin references and future public property URLs.</span></label>
                <label class="field-label md:col-span-2">Street address <span class="text-red-600">*</span><input name="address" id="address_input" value="{{ old('address', $property->address) }}" required placeholder="123 Aura Way" class="input"></label>
                <label class="field-label">City <span class="text-red-600">*</span><input name="city" id="city_input" value="{{ old('city', $property->city) }}" required placeholder="City" class="input"></label>
                <label class="field-label">State<input name="state" id="state_input" value="{{ old('state', $property->state) }}" placeholder="ST" class="input"></label>
                <label class="field-label">ZIP<input name="zip" id="zip_input" value="{{ old('zip', $property->zip) }}" placeholder="ZIP Code" class="input"></label>
                <label class="field-label">Phone<input name="contact_phone" value="{{ old('contact_phone', $property->contact_phone) }}" placeholder="+1 555 123 4567" class="input"></label>
            </div>
        </section>

        @if($property->exists)
        <section class="card card-pad">
            <h2 class="section-title">Rates & billing</h2>
            <p class="section-copy">Saved together with the rest of this form.</p>

            <div class="mt-6 grid gap-8">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Parking rates</h3>
                    <p class="section-copy mt-1">Set a per-night parking rate for each day of the week. Guests who indicate they need parking will be charged the sum of these rates across the nights of their stay, calculated automatically. Leave a day blank to charge $0 for that night.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach(['sunday' => 'Sunday', 'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday'] as $day => $label)
                            <label class="field-label">
                                {{ $label }}
                                <div class="flex items-center gap-1">
                                    <span class="text-slate-500">$</span>
                                    <input type="number" step="0.01" min="0" name="parking_rate_{{ $day }}" value="{{ old('parking_rate_'.$day, $property->{'parking_rate_'.$day}) }}" placeholder="0.00" class="input">
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-8">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Early check-in / late checkout rates</h3>
                    <p class="section-copy mt-1">Flat rates for granted early check-in windows, and per-half-hour rates for late checkout. Authorized late checkout is billed by admin-entered hours (rounded up to the nearest half hour); unauthorized late checkout is billed the same way using the hours between standard checkout and the admin-recorded actual checkout time, at the higher unauthorized rate.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="field-label">
                            Early check-in, 8:00 AM - 12:00 PM
                            <div class="flex items-center gap-1">
                                <span class="text-slate-500">$</span>
                                <input type="number" step="0.01" min="0" name="early_checkin_rate_8am_12pm" value="{{ old('early_checkin_rate_8am_12pm', $property->early_checkin_rate_8am_12pm) }}" placeholder="0.00" class="input">
                            </div>
                        </label>
                        <label class="field-label">
                            Early check-in, 12:00 PM - 2:00 PM
                            <div class="flex items-center gap-1">
                                <span class="text-slate-500">$</span>
                                <input type="number" step="0.01" min="0" name="early_checkin_rate_12pm_2pm" value="{{ old('early_checkin_rate_12pm_2pm', $property->early_checkin_rate_12pm_2pm) }}" placeholder="0.00" class="input">
                            </div>
                        </label>
                        <label class="field-label">
                            Early check-in, 2:00 PM - 4:00 PM
                            <div class="flex items-center gap-1">
                                <span class="text-slate-500">$</span>
                                <input type="number" step="0.01" min="0" name="early_checkin_rate_2pm_4pm" value="{{ old('early_checkin_rate_2pm_4pm', $property->early_checkin_rate_2pm_4pm) }}" placeholder="0.00" class="input">
                            </div>
                        </label>
                        <label class="field-label">
                            Late checkout, authorized (per half hour)
                            <div class="flex items-center gap-1">
                                <span class="text-slate-500">$</span>
                                <input type="number" step="0.01" min="0" name="late_checkout_rate_authorized_per_30min" value="{{ old('late_checkout_rate_authorized_per_30min', $property->late_checkout_rate_authorized_per_30min) }}" placeholder="0.00" class="input">
                            </div>
                        </label>
                        <label class="field-label">
                            Late checkout, unauthorized (per half hour)
                            <div class="flex items-center gap-1">
                                <span class="text-slate-500">$</span>
                                <input type="number" step="0.01" min="0" name="late_checkout_rate_unauthorized_per_30min" value="{{ old('late_checkout_rate_unauthorized_per_30min', $property->late_checkout_rate_unauthorized_per_30min) }}" placeholder="0.00" class="input">
                            </div>
                        </label>
                    </div>
                </div>
            </div>
        </section>
        @endif
        </div>

        <aside class="card card-pad self-start xl:sticky xl:top-6">
            <h2 class="section-title">Property settings</h2>
            <p class="section-copy">Publishing, GPS, and quick settings for this property.</p>

            <label class="mt-5 flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm font-semibold"><span>Active property</span><input type="checkbox" name="active" value="1" @checked(old('active', $property->active ?? true)) class="rounded border-slate-300"></label>
            <label class="mt-3 flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm font-semibold">
                <span>Require license plate photo<br><span class="mt-0.5 block text-xs font-normal text-slate-500">Only asked of guests who say they need parking. Uncheck for properties with no parking to track.</span></span>
                <input type="checkbox" name="requires_vehicle_photo" value="1" @checked(old('requires_vehicle_photo', $property->requires_vehicle_photo ?? true)) class="rounded border-slate-300 shrink-0">
            </label>
            <x-media-image-field
                name="header_image"
                label="Header image"
                :value="$property->header_image"
                preview-class="mt-2 h-36 w-full rounded-xl border border-slate-200 object-cover"
                help="Upload a polished property hero image."
            />

            <div class="mt-6 border-t border-slate-200 pt-6">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">GPS and maps</h3>
                <label class="field-label">Latitude<input name="latitude" id="latitude_input" value="{{ old('latitude', $property->latitude) }}" placeholder="32.715736" class="input" readonly></label>
                <label class="field-label mt-4">Longitude<input name="longitude" id="longitude_input" value="{{ old('longitude', $property->longitude) }}" placeholder="-117.161087" class="input" readonly></label>
                <label class="field-label mt-4">
                    Timezone
                    <select name="timezone" class="input mt-1">
                        @foreach(timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}" {{ old('timezone', $property->timezone ?? 'America/New_York') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </select>
                    <span class="field-help">Auto-detected from coordinates. Override if needed.</span>
                </label>
            </div>

            @if($property->exists)
            <div class="mt-6 border-t border-slate-200 pt-6">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Quick settings</h3>
                <label class="field-label">Incidentals threshold (USD)
                    <input type="number" step="0.01" min="0" name="deposit_cap_dollars" value="{{ old('deposit_cap_dollars', $property->deposit_cap_cents !== null ? number_format($property->deposit_cap_cents / 100, 2, '.', '') : '') }}" placeholder="e.g. 150.00" class="input">
                    <p class="mt-1 text-xs text-slate-500">Parking fees plus incidentals will be capped at this amount for the property. A global processing fee percentage (set in Admin Settings) is applied on top.</p>
                    <span class="field-help">Leave blank to use the global default.</span>
                </label>
                <label class="field-label mt-4">Required incidentals hold (USD)
                    <input type="number" step="0.01" min="0" name="required_incidentals_hold_amount" value="{{ old('required_incidentals_hold_amount', $property->required_incidentals_hold_amount) }}" placeholder="e.g. 200.00" class="input">
                    <p class="mt-1 text-xs text-slate-500">Default incidentals hold for every booking at this property.</p>
                </label>
                <label class="field-label mt-4">Channex property ID
                    <input name="channex_property_id" value="{{ old('channex_property_id', $property->channex_property_id) }}" placeholder="e.g. 3f9a2b10-..." class="input">
                    <span class="field-help">Bookings won't import until this is set.</span>
                </label>
                <div class="mt-4 grid grid-cols-2 gap-4">
                    <label class="field-label">Check-in time
                        <input type="time" name="checkin_time" value="{{ old('checkin_time', $property->checkin_time ?? '16:00') }}" class="input">
                    </label>
                    <label class="field-label">Check-out time
                        <input type="time" name="checkout_time" value="{{ old('checkout_time', $property->checkout_time ?? '11:00') }}" class="input">
                    </label>
                </div>
                <label class="field-label mt-4">Lockbox code
                    <input name="lockbox_code" value="{{ old('lockbox_code', $property->lockbox_code) }}" placeholder="4521" class="input">
                </label>
            </div>
            @endif

            <button class="btn-primary mt-6 w-full">Save property</button>
        </aside>

        <input type="hidden" name="map_embed_url" id="map_embed_url_input" value="{{ old('map_embed_url', $property->map_embed_url) }}">
        <input type="hidden" name="map_directions_url" id="map_directions_url_input" value="{{ old('map_directions_url', $property->map_directions_url) }}">
    </form>

    @if($property->exists)
    {{-- ── Smart locks ──────────────────────────────────────────────── --}}
    {{-- Kept outside the main form: each lock is its own resource with its own
         add/edit/remove action, not a "settings" field to be saved together. --}}
    <div class="mt-10">
        <h2 class="mb-4 text-lg font-semibold text-slate-950">Smart locks</h2>
        <div class="card card-pad">
            <div class="mb-1 flex items-center justify-between">
                <h3 class="section-title">Devices</h3>
                <span class="text-sm text-slate-500">{{ $property->locks->count() }} lock{{ $property->locks->count() === 1 ? '' : 's' }}</span>
            </div>
            @if($property->locks->count())
                <div class="mb-5 mt-4 grid gap-3">
                    @foreach($property->locks as $lock)
                        <div class="rounded-xl border border-slate-200 p-4" id="lock-row-{{ $lock->id }}">
                            <div class="flex items-center justify-between gap-3" id="lock-view-{{ $lock->id }}">
                                <div>
                                    <p class="font-bold text-slate-950">{{ $lock->label }}</p>
                                    <p class="text-sm text-slate-500">
                                        Device ID: {{ $lock->seam_device_id }}
                                        @if($lock->manufacturer) &middot; {{ ucfirst($lock->manufacturer) }} @endif
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="btn-secondary" onclick="toggleLockEdit({{ $lock->id }})">Edit</button>
                                    <form method="post" action="{{ route('admin.properties.locks.destroy', [$property, $lock]) }}" onsubmit="return confirm('Remove this lock?');">
                                        @csrf @method('delete')
                                        <button class="btn-secondary text-red-600">Remove</button>
                                    </form>
                                </div>
                            </div>

                            <form method="post" action="{{ route('admin.properties.locks.update', [$property, $lock]) }}" class="mt-4 hidden grid gap-4 md:grid-cols-3" id="lock-edit-{{ $lock->id }}">
                                @csrf @method('put')
                                <label class="field-label md:col-span-1">Label<input name="label" value="{{ $lock->label }}" placeholder="Front Door" class="input"></label>
                                <label class="field-label md:col-span-1">Seam Device ID <span class="text-red-600">*</span><input name="seam_device_id" value="{{ $lock->seam_device_id }}" required placeholder="device_xxxxxxxx" class="input"></label>
                                <label class="field-label md:col-span-1">Manufacturer<input name="manufacturer" value="{{ $lock->manufacturer }}" placeholder="august, yale" class="input"></label>
                                <div class="md:col-span-3 flex gap-2">
                                    <button class="btn-primary">Save</button>
                                    <button type="button" class="btn-secondary" onclick="toggleLockEdit({{ $lock->id }})">Cancel</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
                <script>
                    function toggleLockEdit(id) {
                        document.getElementById('lock-view-' + id).classList.toggle('hidden');
                        document.getElementById('lock-edit-' + id).classList.toggle('hidden');
                    }
                </script>
            @else
                <p class="mb-5 mt-4 text-slate-500">No locks configured yet for {{ $property->name }}.</p>
            @endif
            <form method="post" action="{{ route('admin.properties.locks.store', $property) }}" class="grid gap-4 border-t border-slate-200 pt-5 md:grid-cols-3">
                @csrf
                <label class="field-label md:col-span-1">Label<input name="label" placeholder="Front Door" class="input"></label>
                <label class="field-label md:col-span-1">Seam Device ID <span class="text-red-600">*</span><input name="seam_device_id" required placeholder="device_xxxxxxxx" class="input"></label>
                <label class="field-label md:col-span-1">Manufacturer<input name="manufacturer" placeholder="august, yale" class="input"></label>
                <div class="md:col-span-3">
                    <button class="btn-primary">Add Lock</button>
                </div>
            </form>
        </div>
    </div>
    @endif

<script>
// Force gmp-place-autocomplete's shadow DOM open so we can style its real
// internal input/focus-ring instead of the host element (which was causing
// the "double box" look — our old CSS styled the host AND the shadow content
// both rendered their own bordered boxes).
(function() {
    if (window.__gmpShadowPatched) return;
    window.__gmpShadowPatched = true;
    const originalAttachShadow = Element.prototype.attachShadow;
    Element.prototype.attachShadow = function(init) {
        if (this.localName === 'gmp-place-autocomplete') {
            const shadow = originalAttachShadow.call(this, { ...init, mode: 'open' });
            const style = document.createElement('style');
            style.textContent = `
                .input-container {
                    border: 1px solid #cbd5e1 !important;
                    border-radius: 0.375rem !important;
                    box-shadow: none !important;
                    background-color: #ffffff !important;
                    padding-left: 0.75rem !important;
                    padding-right: 0.75rem !important;
                }
                .input-container:focus-within {
                    border-color: #2563eb !important;
                    box-shadow: 0 0 0 4px #dbeafe !important;
                }
                .focus-ring {
                    display: none !important;
                }
                input {
                    outline: none !important;
                    box-shadow: none !important;
                    font-size: 0.875rem !important;
                }
            `;
            shadow.appendChild(style);
            return shadow;
        }
        return originalAttachShadow.call(this, init);
    };
})();

async function initAutocomplete() {
    const { PlaceAutocompleteElement } = await google.maps.importLibrary("places");
    const oldInput = document.getElementById('address_input');

    const autocompleteEl = new PlaceAutocompleteElement({
        includedPrimaryTypes: ['street_address', 'premise', 'subpremise'],
    });
    autocompleteEl.id = 'address_input_new';
    oldInput.type = 'hidden';
    oldInput.parentNode.insertBefore(autocompleteEl, oldInput);

    if (oldInput.value) {
        autocompleteEl.value = oldInput.value;
    }

    // Keep the hidden required field in sync with whatever the user types,
    // even if they never click a dropdown suggestion. Prevents the form
    // from silently blocking submit on a hidden required input.
    autocompleteEl.addEventListener('input', () => {
        oldInput.value = autocompleteEl.value || '';
    });

    autocompleteEl.addEventListener('gmp-select', async ({ placePrediction }) => {
        const place = placePrediction.toPlace();
        await place.fetchFields({
            fields: ['displayName', 'formattedAddress', 'location', 'addressComponents']
        });

        oldInput.value = place.formattedAddress || '';

        const lat = place.location.lat();
        const lng = place.location.lng();
        document.getElementById('latitude_input').value = lat;
        document.getElementById('longitude_input').value = lng;
        document.getElementById('map_embed_url_input').value =
            'https://www.google.com/maps?q=' + lat + ',' + lng + '&output=embed';
        document.getElementById('map_directions_url_input').value =
            'https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng;

        let city = '', state = '', zip = '';
        for (const component of (place.addressComponents || [])) {
            const types = component.types;
            if (types.includes('locality')) city = component.longText;
            if (types.includes('administrative_area_level_1')) state = component.shortText;
            if (types.includes('postal_code')) zip = component.longText;
        }
        if (city) document.getElementById('city_input').value = city;
        if (state) document.getElementById('state_input').value = state;
        if (zip) document.getElementById('zip_input').value = zip;

        // Auto-detect timezone from coordinates
        const tzUrl = 'https://maps.googleapis.com/maps/api/timezone/json?location=' + lat + ',' + lng + '&timestamp=' + Math.floor(Date.now()/1000) + '&key={{ config("services.google_maps.key") }}';
        fetch(tzUrl).then(r => r.json()).then(data => {
            if (data.timeZoneId) {
                const sel = document.querySelector('select[name="timezone"]');
                if (sel) sel.value = data.timeZoneId;
            }
        });
    });
}
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places&callback=initAutocomplete&loading=async" async defer></script>
<style>
gmp-place-autocomplete {
    width: 100%;
    display: block;
    color-scheme: light;
}
</style>

</x-admin-layout>
