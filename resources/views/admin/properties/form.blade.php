<x-admin-layout :title="$property->exists ? 'Edit Property' : 'Add Property'">
    <div class="page-header">
        <div>
            <p class="eyebrow">Property setup</p>
            <h1 class="page-title">{{ $property->exists ? 'Edit property' : 'Add property' }}</h1>
            <p class="page-subtitle">Configure the public guest page, GPS arrival point, maps, images, and all arrival instructions for this unit.</p>
        </div>
        <a href="{{ $returnTo ?? route('admin.properties.index') }}" class="btn-secondary">Back</a>
    </div>

    @if($property->exists)
    <section class="card card-pad mb-6" id="lockbox-quick">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="section-title">Lockbox code</h2>
            <span id="lockbox-quick-msg" class="text-sm"></span>
        </div>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row">
            <input id="lockbox-quick-input" type="text" inputmode="text" autocomplete="off" value="{{ $property->lockbox_code }}" placeholder="4521" class="input flex-1 font-mono text-2xl tracking-widest" aria-label="Lockbox code">
            <button type="button" id="lockbox-quick-save" class="rounded-lg bg-slate-900 px-6 py-3 text-base font-semibold text-white hover:bg-slate-700 sm:w-auto w-full">Save code</button>
        </div>
    </section>
    <script>
        (function () {
            var input = document.getElementById('lockbox-quick-input');
            var btn = document.getElementById('lockbox-quick-save');
            var msg = document.getElementById('lockbox-quick-msg');
            btn.addEventListener('click', function () {
                btn.disabled = true;
                msg.textContent = 'Saving...';
                msg.className = 'text-sm text-slate-500';
                fetch(@json(route('admin.properties.lockbox.update', $property)), {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ lockbox_code: input.value })
                }).then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                }).then(function () {
                    msg.textContent = 'Saved';
                    msg.className = 'text-sm font-semibold text-emerald-700';
                }).catch(function (e) {
                    msg.textContent = 'Could not save (' + e.message + ')';
                    msg.className = 'text-sm font-semibold text-red-700';
                }).finally(function () { btn.disabled = false; });
            });
        })();
    </script>
    @endif

    <form method="post" enctype="multipart/form-data" action="{{ $property->exists ? route('admin.properties.update', $property) : route('admin.properties.store') }}" class="grid min-w-0 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        @csrf @if($property->exists) @method('put') @endif
        <input type="hidden" name="return_to" value="{{ $returnTo ?? '' }}">

        <div class="grid min-w-0 content-start gap-6">

        <section id="general" class="card card-pad">
            <h2 class="section-title">Property details</h2>
            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <label class="field-label">Name <span class="text-red-600">*</span><x-help-tip text="The property's name as guests and staff see it, e.g. Lumina Hotel & Residences." /><input name="name" value="{{ old('name', $property->name) }}" required placeholder="Lumina Hotel & Residences" class="input">@error('name')<span class="mt-1 block text-xs text-red-700">{{ $message }}</span>@enderror</label>
                <label class="field-label">Slug<x-help-tip text="The short, URL-safe version of the name used in web addresses. Leave blank to generate it from the name." /><input name="slug" value="{{ old('slug', $property->slug) }}" placeholder="Auto-generated from name" class="input"></label>
                <label class="field-label md:col-span-2">Street address <span class="text-red-600">*</span><x-help-tip text="The street address of the property. Pick a suggestion to fill in city, state, ZIP, coordinates and timezone automatically." /><input name="address" id="address_input" value="{{ old('address', $property->address) }}" required placeholder="123 Aura Way" class="input"></label>
                <label class="field-label">City <span class="text-red-600">*</span><x-help-tip text="The city where the property is located. Filled in automatically when you pick an address." /><input name="city" id="city_input" value="{{ old('city', $property->city) }}" required placeholder="City" class="input"></label>
                <label class="field-label">State<x-help-tip text="The state or region abbreviation. Filled in automatically when you pick an address." /><input name="state" id="state_input" value="{{ old('state', $property->state) }}" placeholder="ST" class="input"></label>
                <label class="field-label">ZIP<x-help-tip text="The postal code. Filled in automatically when you pick an address." /><input name="zip" id="zip_input" value="{{ old('zip', $property->zip) }}" placeholder="ZIP Code" class="input"></label>
                <label class="field-label">Phone<x-help-tip text="A phone number guests can use to reach the host or property manager about this property." /><input name="contact_phone" value="{{ old('contact_phone', $property->contact_phone) }}" placeholder="+1 555 123 4567" class="input"></label>
                <label class="field-label">Contact email<x-help-tip text="An email address guests can use to reach the host or property manager about this property." /><input type="email" name="contact_email" value="{{ old('contact_email', $property->contact_email) }}" placeholder="host@example.com" class="input"></label>
                <label class="field-label">Property owner
                    <x-help-tip text="The owner or company this property belongs to. It decides who can see and manage the property." /><select name="owner_id" class="input">
                        <option value="">Unassigned</option>
                        @foreach($owners as $ownerId => $ownerName)
                            <option value="{{ $ownerId }}" @selected((string) old('owner_id', $property->owner_id) === (string) $ownerId)>{{ $ownerName }}</option>
                        @endforeach
                    </select>
                    
                </label>
            </div>
        </section>

        @php
            $activeContractTab = $errors->has('lease_agreement') ? 'lease' : 'rental';
        @endphp
        <div class="mb-3 flex flex-wrap gap-2" role="tablist" aria-label="Property contract editors">
            <button type="button" id="rental-contract-tab" data-contract-tab="rental" role="tab" aria-controls="rental-contract-panel" aria-selected="{{ $activeContractTab === 'rental' ? 'true' : 'false' }}" onclick="switchContractTab('rental')" class="contract-tab-btn btn-secondary {{ $activeContractTab === 'rental' ? 'bg-slate-900 text-white' : '' }}">Rental agreement</button>
            <button type="button" id="lease-contract-tab" data-contract-tab="lease" role="tab" aria-controls="lease-contract-panel" aria-selected="{{ $activeContractTab === 'lease' ? 'true' : 'false' }}" onclick="switchContractTab('lease')" class="contract-tab-btn btn-secondary {{ $activeContractTab === 'lease' ? 'bg-slate-900 text-white' : '' }}">Long-term lease</button>
        </div>
        <div id="rental-contract-panel" class="contract-panel min-w-0 w-full" role="tabpanel" aria-labelledby="rental-contract-tab" style="{{ $activeContractTab !== 'rental' ? 'position:absolute;left:-9999px;top:-9999px;visibility:hidden;' : 'position:relative;' }}">
        <style>
        #rental-agreement .tox-tinymce { border: 1px solid #cbd5e1 !important; border-radius: 0.5rem !important; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,0.05); }
        #rental-agreement .tox-tinymce:focus-within { border-color: #64748b !important; }
        #rental-agreement .tox .tox-editor-header { border-bottom: 1px solid #e2e8f0 !important; box-shadow: none !important; }
        #rental-agreement .tox .tox-statusbar { border-top: 1px solid #e2e8f0 !important; }
        </style>
        <section id="rental-agreement" class="card card-pad">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="section-title">Rental agreement</h2>
                    <p class="mt-1 text-sm text-slate-500">This starts as your default agreement. Edit it to customize it for this property. Clear it completely to keep using the default.</p>
                </div>
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-800">
                    <input type="checkbox" id="skip_rental_agreement_box" name="skip_rental_agreement" value="1" @checked(old('skip_rental_agreement', $property->skip_rental_agreement ?? false)) class="rounded border-slate-300 shrink-0">
                    No agreement for this property
                </label>
            </div>

            <div id="rental-agreement-body" class="mt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Click to insert a placeholder</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach(['guest_name','guest_first_name','property_name','property_address','check_in_date','check_out_date','check_in_time','check_out_time','host_name','website_name','reservation_id','booking_id','guest_id_type','id_photo','signed_name','signed_at','signed_ip','signed_device_id','signed_browser','agreement_version'] as $ph)
                        <button type="button" data-ph="[[{{ $ph }}]]" class="ra-chip rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">[[{{ $ph }}]]</button>
                    @endforeach
                </div>
                @php
                    $raDefault = '';
                    foreach (['legal_rental_contract_content', 'rental_contract_content', 'legal_rental_agreement_content'] as $raKey) {
                        $raVal = \App\Models\Setting::getValue($raKey, '');
                        if (filled($raVal)) { $raDefault = $raVal; break; }
                    }
                    $raText = filled($property->rental_agreement) ? $property->rental_agreement : app(\App\Services\RentalAgreementService::class)->defaultTemplate($raDefault);
                @endphp
                <textarea id="rental_agreement_editor" name="rental_agreement" rows="16" class="input mt-3 w-full">{{ old('rental_agreement', $raText) }}</textarea>
                @error('rental_agreement')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>
        </div>
        <div id="lease-contract-panel" class="contract-panel min-w-0 w-full" role="tabpanel" aria-labelledby="lease-contract-tab" style="{{ $activeContractTab !== 'lease' ? 'position:absolute;left:-9999px;top:-9999px;visibility:hidden;' : 'position:relative;' }}">
        @php
            $leaseSvc = app(\App\Services\LeaseAgreementService::class);
            $leaseSeed = $leaseSvc->seedFor($property);
            $leaseText = filled($property->lease_agreement) ? $property->lease_agreement : $leaseSeed;
        @endphp
        <style>
        #lease-agreement .tox-tinymce { border: 1px solid #cbd5e1 !important; border-radius: 0.5rem !important; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,0.05); }
        #lease-agreement .tox-tinymce:focus-within { border-color: #64748b !important; }
        #lease-agreement .tox .tox-editor-header { border-bottom: 1px solid #e2e8f0 !important; box-shadow: none !important; }
        #lease-agreement .tox .tox-statusbar { border-top: 1px solid #e2e8f0 !important; }
        </style>
        <section id="lease-agreement" class="card card-pad">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="section-title">Long-term lease contract</h2>
                    <p class="mt-1 text-sm text-slate-500">Used for lease applications (stays of 29 nights or longer). It starts from this property's rental agreement terms. When you approve an application you redraft it for that tenant, review it and sign before it is sent.</p>
                </div>
                <button type="button" id="lease-reset-btn" data-seed="{{ $leaseSeed }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50">Reset from rental agreement</button>
            </div>
            <div class="mt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Click to insert a placeholder</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($leaseSvc->placeholders() as $ph)
                        <button type="button" data-ph="[[{{ $ph }}]]" class="lease-chip rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100">[[{{ $ph }}]]</button>
                    @endforeach
                </div>
                <textarea id="lease_agreement_editor" name="lease_agreement" rows="16" class="input mt-3 w-full">{{ old('lease_agreement', $leaseText) }}</textarea>
                @error('lease_agreement')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
        </section>
        <script>
        (function () {
            var tries = 0;
            function leaseEditor() { return window.tinymce ? tinymce.get('lease_agreement_editor') : null; }
            function boot() {
                if (!window.tinymce) {
                    if (tries++ < 100) { setTimeout(boot, 200); }
                    return;
                }
                tinymce.init({
                    selector: '#lease_agreement_editor',
                    height: 460,
                    menubar: false,
                    branding: false,
                    promotion: false,
                    plugins: 'lists link table code',
                    content_style: 'body { font-family: system-ui, sans-serif; font-size: 14px; padding: 12px; }',
                    toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link table | removeformat code'
                });
            }
            boot();
            document.querySelectorAll('.lease-chip').forEach(function (b) {
                b.addEventListener('click', function () {
                    var ed = leaseEditor();
                    if (ed) { ed.focus(); ed.insertContent(b.dataset.ph); }
                });
            });
            var reset = document.getElementById('lease-reset-btn');
            if (reset) {
                reset.addEventListener('click', function () {
                    var ed = leaseEditor();
                    if (ed && confirm('Replace the lease contract with a fresh copy built from the rental agreement terms?')) {
                        ed.setContent(reset.dataset.seed);
                    }
                });
            }
        })();
        </script>
        <script>
        (function () {
            var box = document.getElementById('skip_rental_agreement_box');
            var body = document.getElementById('rental-agreement-body');
            function editor() { return window.tinymce ? tinymce.get('rental_agreement_editor') : null; }
            function applySkip() {
                var off = box.checked;
                body.style.opacity = off ? '0.45' : '1';
                body.style.pointerEvents = off ? 'none' : 'auto';
                var ed = editor();
                if (ed) ed.mode.set(off ? 'readonly' : 'design');
            }
            function init() {
                tinymce.init({
                    selector: '#rental_agreement_editor',
                    height: 460,
                    menubar: false,
                    branding: false,
                    promotion: false,
                    plugins: 'lists link table code',
                    content_style: 'body { font-family: system-ui, sans-serif; font-size: 14px; padding: 12px; }',
                    toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link table | removeformat code',
                    setup: function (ed) { ed.on('init', applySkip); }
                });
            }
            if (window.tinymce) { init(); }
            else {
                var s = document.createElement('script');
                s.src = 'https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.3/tinymce.min.js';
                s.onload = init;
                document.head.appendChild(s);
            }
            box.addEventListener('change', applySkip);
            document.querySelectorAll('.ra-chip').forEach(function (b) {
                b.addEventListener('click', function () {
                    var ed = editor();
                    if (ed) { ed.focus(); ed.insertContent(b.dataset.ph); }
                });
            });
        })();
        </script>
        </div>
        <script>
        function switchContractTab(key) {
            document.querySelectorAll('.contract-panel').forEach(function (panel) {
                var isTarget = panel.id === key + '-contract-panel';
                panel.style.position = isTarget ? 'relative' : 'absolute';
                panel.style.left = isTarget ? '' : '-9999px';
                panel.style.top = isTarget ? '' : '-9999px';
                panel.style.visibility = isTarget ? 'visible' : 'hidden';
            });
            document.querySelectorAll('.contract-tab-btn').forEach(function (button) {
                var isTarget = button.dataset.contractTab === key;
                button.setAttribute('aria-selected', isTarget ? 'true' : 'false');
                button.classList.toggle('bg-slate-900', isTarget);
                button.classList.toggle('text-white', isTarget);
            });
            window.dispatchEvent(new Event('resize'));
        }
        </script>

        @if($property->exists)
        <section class="card card-pad">
            <h2 class="section-title">Rates & billing</h2>

            <div class="mt-6 grid gap-8">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Parking rates<x-help-tip text="The nightly parking fee for each day of the week. Guests who need parking are charged the sum of the rates for the nights they stay." /></h3>
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
                    <h3 class="text-sm font-bold uppercase tracking-wide text-slate-500">Early check-in / late checkout rates<x-help-tip text="Flat fees for early check-in windows, and per-half-hour fees for late checkout, depending on whether it was authorized." /></h3>
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

        <aside class="card card-pad min-w-0 self-start xl:sticky xl:top-6">
            <h2 class="section-title">Property settings</h2>

            <label class="mt-5 flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm font-semibold"><span>Active property<x-help-tip text="Inactive properties are hidden from lists and scheduling but keep all their data." /></span><input type="checkbox" name="active" value="1" @checked(old('active', $property->active ?? true)) class="rounded border-slate-300"></label>
            <label class="mt-3 flex items-center justify-between rounded-xl border border-slate-200 p-4 text-sm font-semibold">
                <span>Require license plate photo<x-help-tip text="Only asked of guests who say they need parking. Turn it off for properties with no parking." /></span>
                <input type="checkbox" name="requires_vehicle_photo" value="1" @checked(old('requires_vehicle_photo', $property->requires_vehicle_photo ?? true)) class="rounded border-slate-300 shrink-0">
            </label>
            <x-media-image-field
                name="header_image"
                label="Header image"
                :value="$property->header_image"
                preview-class="mt-2 h-36 w-full rounded-xl border border-slate-200 object-cover"
                help="The main banner photo shown at the top of the guest page for this property."
            />
            <label class="field-label mt-4">Cleaning property photo
                <x-help-tip text="A photo of the property shown in property lists and to cleaners. Separate from the guest header image." /><input type="file" name="photo" accept="image/*" class="input">
                @if($property->photo_path)
                    <span class="mt-1 block text-xs text-slate-500">A Cleaning photo is already stored. Upload a new image to replace it.</span>
                    <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_photo" value="1"> Remove current Cleaning photo</label>
                @endif
            </label>

            <div id="cleaning-setup" class="mt-6 border-t border-slate-200 pt-6">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">GPS and maps</h3>
                <label class="field-label">Latitude<x-help-tip text="The property's map position. Set automatically when you pick an address." /><input name="latitude" id="latitude_input" value="{{ old('latitude', $property->latitude) }}" placeholder="32.715736" class="input" readonly></label>
                <label class="field-label mt-4">Longitude<x-help-tip text="The property's map position. Set automatically when you pick an address." /><input name="longitude" id="longitude_input" value="{{ old('longitude', $property->longitude) }}" placeholder="-117.161087" class="input" readonly></label>
                <label class="field-label mt-4">
                    Timezone
                    <x-help-tip text="The property's local timezone, used for check-in, check-out and cleaning times. Detected from the address; change it only if it is wrong." /><select name="timezone" class="input mt-1">
                        @foreach(timezone_identifiers_list() as $tz)
                            <option value="{{ $tz }}" {{ old('timezone', $property->timezone ?? 'America/New_York') === $tz ? 'selected' : '' }}>{{ $tz }}</option>
                        @endforeach
                    </select>
                    
                </label>
            </div>


            @if($property->exists)
            <div class="mt-6 border-t border-slate-200 pt-6">
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Quick settings</h3>
                <label class="field-label">Incidentals threshold (USD)
                    <x-help-tip text="The most that parking fees plus incidentals can add up to for one booking at this property. Leave blank to use the global default." /><input type="number" step="0.01" min="0" name="deposit_cap_dollars" value="{{ old('deposit_cap_dollars', $property->deposit_cap_cents !== null ? number_format($property->deposit_cap_cents / 100, 2, '.', '') : '') }}" placeholder="e.g. 150.00" class="input">
                    
                    
                </label>
                <label class="field-label mt-4">Required incidentals hold (USD)
                    <x-help-tip text="The amount held for incidentals by default on every booking at this property." /><input type="number" step="0.01" min="0" name="required_incidentals_hold_amount" value="{{ old('required_incidentals_hold_amount', $property->required_incidentals_hold_amount) }}" placeholder="e.g. 200.00" class="input">
                    
                </label>
                <label class="field-label mt-4">Channex property ID
                    <x-help-tip text="This property's ID in Channex, your booking channel manager. Bookings will not import until it is set." /><input name="channex_property_id" value="{{ old('channex_property_id', $property->channex_property_id) }}" placeholder="e.g. 3f9a2b10-..." class="input">
                    
                </label>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="field-label">Check-in time
                        <x-help-tip text="The standard check-in time shown to guests." /><input type="time" name="checkin_time" value="{{ old('checkin_time', $property->checkin_time ?? '16:00') }}" class="input">
                    </label>
                    <label class="field-label">Check-out time
                        <x-help-tip text="The standard check-out time shown to guests." /><input type="time" name="checkout_time" value="{{ old('checkout_time', $property->checkout_time ?? '11:00') }}" class="input">
                    </label>
                </div>
                @unless($property->exists)
                <label class="field-label mt-4">Lockbox code
                    <x-help-tip text="The code for the key lockbox at the property." /><input name="lockbox_code" value="{{ old('lockbox_code', $property->lockbox_code) }}" placeholder="4521" class="input">
                </label>
                @endunless
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
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" id="lock-view-{{ $lock->id }}">
                                <div class="min-w-0">
                                    <p class="font-bold text-slate-950">{{ $lock->label }}</p>
                                    <p class="break-all text-sm text-slate-500">
                                        Device ID: {{ $lock->seam_device_id }}
                                        @if($lock->manufacturer) &middot; {{ ucfirst($lock->manufacturer) }} @endif
                                    </p>
                                </div>
                                <div class="flex gap-2 sm:shrink-0">
                                    <button type="button" class="btn-secondary flex-1 sm:flex-none" onclick="toggleLockEdit({{ $lock->id }})">Edit</button>
                                    <form method="post" action="{{ route('admin.properties.locks.destroy', [$property, $lock]) }}" class="flex-1 sm:flex-none" onsubmit="return confirm('Remove this lock?');">
                                        @csrf @method('delete')
                                        <button class="btn-secondary w-full text-red-600 sm:w-auto">Remove</button>
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
