<x-admin-layout title="Direct booking listing">
    @php
        $descriptionValue = old('public_description', $listing->public_description);
        preg_match_all('/[\p{L}\p{N}]+(?:[\'’][\p{L}\p{N}]+)*/u', strip_tags((string) $descriptionValue), $descriptionMatches);
        $descriptionWords = count($descriptionMatches[0]);
        $sameDayCutoffTime = old('same_day_cutoff_time', $listing->same_day_cutoff_time);
        if (is_string($sameDayCutoffTime) && preg_match('/\A\d{2}:\d{2}(?::\d{2})?\z/', $sameDayCutoffTime)) {
            $sameDayCutoffTime = substr($sameDayCutoffTime, 0, 5);
        }
        $photoCount = $listing->exists ? $listing->photos->count() : 0;
        $methodsValue = old('payment_methods', $paymentMethods->map(fn ($method) => [
            'type' => $method->type,
            'label' => $method->label,
            'handle' => $method->handle,
            'instructions' => $method->instructions,
            'enabled' => $method->enabled,
        ])->all());
        if ($methodsValue === []) {
            $methodsValue = [['type' => '', 'label' => '', 'handle' => '', 'instructions' => '', 'enabled' => true]];
        }
    @endphp

    <style>
        #listing-form{grid-template-columns:minmax(0,1fr);width:100%;min-width:0}
        #listing-form>div,#listing-form section,#listing-form label{min-width:0}
        #listing-form .grid{min-width:0}
        #listing-form input:not([type=checkbox]):not([type=radio]),#listing-form select,#listing-form textarea{width:100%;max-width:100%;min-width:0;box-sizing:border-box}
        #listing-form .field-label>div.flex{min-width:0}
        #listing-form .field-label>div.flex input{min-width:0;flex:1 1 auto}
        @media(min-width:768px){#listing-form .listing-responsive-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(min-width:1280px){#listing-form{grid-template-columns:minmax(0,1fr) 340px}}
        @media(max-width:640px){#listing-form{gap:16px}#listing-form .card-pad{padding:16px}#listing-form .listing-responsive-grid{grid-template-columns:minmax(0,1fr)}#listing-form .listing-responsive-grid>*{grid-column:1/-1}#listing-form .listing-photo-card{min-width:0}}
    </style>
    <div class="page-header">
        <div>
            <p class="eyebrow">Direct booking</p>
            <h1 class="page-title">{{ $property->name }} listing</h1>
            <p class="page-subtitle">Create and preview the public-facing details. Unpublished listings remain private.</p>
        </div>
        <a href="{{ route('admin.properties.index') }}" class="btn-secondary">Back to properties</a>
    </div>

    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
            <strong>The listing could not be saved:</strong>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if(session('stripe_notice'))
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">{{ session('stripe_notice') }}</div>
    @endif

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <p class="font-semibold">Please fix the following before saving:</p>
            <ul class="mt-2 list-inside list-disc">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form id="listing-form" method="post" enctype="multipart/form-data" action="{{ route('admin.properties.listing.save', $property) }}" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        @csrf
        @method('put')
        <div class="grid gap-6">
            <section class="card card-pad">
                <h2 class="section-title">Public details</h2>
                <p class="section-copy">Use accurate, guest-facing information. The street address and private access details are not part of this listing.</p>
                <div class="mt-5 listing-responsive-grid grid gap-5 md:grid-cols-2">
                    <label class="field-label md:col-span-2">Listing title
                        <input name="public_title" value="{{ old('public_title', $listing->public_title) }}" maxlength="255" class="input" placeholder="A clear, descriptive title">
                        @error('public_title')<span class="field-help text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label class="field-label">Listing URL slug
                        <input name="listing_slug" value="{{ old('listing_slug', $listing->listing_slug) }}" class="input" placeholder="Generated from the title">
                        <span class="field-help">Lowercase letters, numbers, and hyphens.</span>
                        @error('listing_slug')<span class="field-help text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label class="field-label">Maximum guests
                        <input type="number" min="1" max="100" name="max_guests" value="{{ old('max_guests', $listing->max_guests) }}" class="input">
                        @error('max_guests')<span class="field-help text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label class="field-label">Bedrooms
                        <input type="number" min="0" max="100" name="bedrooms" value="{{ old('bedrooms', $listing->bedrooms ?? $property->beds) }}" class="input">
                    </label>
                    <label class="field-label md:col-span-2">Description
                        <textarea name="public_description" rows="9" maxlength="30000" class="textarea">{{ $descriptionValue }}</textarea>
                        <span class="field-help">{{ $descriptionWords }} words; publishing requires at least 150. An empty description gets a factual draft generated from the property details and amenities.</span>
                        @error('public_description')<span class="field-help text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label class="field-label md:col-span-2">House rules
                        <textarea name="house_rules" rows="5" class="textarea" maxlength="20000">{{ old('house_rules', $listing->house_rules) }}</textarea>
                    </label>
                </div>
            </section>

            <section class="card card-pad">
                <h2 class="section-title">Photos</h2>
                <p class="section-copy">Select multiple property photos at once. Images are resized into WebP versions for listing display.</p>
                <div class="mt-4">
                    <label class="field-label">Add photos
                        <input type="file" name="photos[]" accept="image/*" multiple class="input">
                        <span class="field-help">Up to 30 images per save, 15 MB each.</span>
                        @error('photos')<span class="field-help text-red-700">{{ $message }}</span>@enderror
                    </label>
                </div>

                <div id="listing-photo-sort" class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($listing->photos as $photo)
                        <article class="listing-photo-card rounded-xl border border-slate-200 p-3" draggable="true">
                            <input type="hidden" name="photo_order[]" value="{{ $photo->id }}">
                            <img src="{{ url('/img/'.$photo->thumbnail_path) }}" alt="" class="h-36 w-full rounded-lg bg-slate-100 object-cover">
                            <div class="mt-3 flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold text-slate-500">Drag to reorder</span>
                                <label class="flex items-center gap-2 text-xs text-rose-700">
                                    <input type="checkbox" name="remove_photo_ids[]" value="{{ $photo->id }}">
                                    Remove
                                </label>
                            </div>
                            <label class="field-label mt-3">Alt text
                                <input name="photo_alt[{{ $photo->id }}]" value="{{ old('photo_alt.'.$photo->id, $photo->alt) }}" maxlength="255" class="input">
                            </label>
                        </article>
                    @endforeach
                </div>
                @error('photo_order')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
                @error('photo_alt')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
            </section>

            <section class="card card-pad">
                <h2 class="section-title">Pricing and booking rules</h2>
                <div class="mt-5 listing-responsive-grid grid gap-5 md:grid-cols-2">
                    <label class="field-label">Base nightly rate (USD)
                        <div class="flex items-center gap-2"><span class="text-slate-500">$</span><input inputmode="decimal" name="base_rate" value="{{ old('base_rate', $listing->base_rate_cents !== null ? number_format($listing->base_rate_cents / 100, 2, '.', '') : '') }}" class="input" placeholder="0.00"></div>
                        @error('base_rate')<span class="field-help text-red-700">{{ $message }}</span>@enderror
                    </label>
                    <label class="field-label">Tax rate (%)
                        <input type="number" name="tax_percent" min="0" max="100" step="0.01" value="{{ old('tax_percent', $listing->tax_percent) }}" class="input">
                    </label>
                    <label class="field-label">Minimum nights
                        <input type="number" name="min_nights" min="1" max="365" value="{{ old('min_nights', $listing->min_nights ?? 1) }}" class="input">
                    </label>
                    <label class="field-label">Booking mode
                        <select name="booking_mode" class="input">
                            <option value="instant" @selected(old('booking_mode', $listing->booking_mode ?? 'instant') === 'instant')>Instant book</option>
                            <option value="request" @selected(old('booking_mode', $listing->booking_mode) === 'request')>Request to book</option>
                        </select>
                    </label>
                    <label class="field-label">Advance notice (hours)
                        <input type="number" name="advance_notice_hours" min="0" max="8760" value="{{ old('advance_notice_hours', $listing->advance_notice_hours ?? 24) }}" class="input">
                    </label>
                    <label class="field-label">Same-day booking cutoff
                        <input type="time" name="same_day_cutoff_time" value="{{ $sameDayCutoffTime }}" class="input">
                    </label>
                    <label class="field-label">Refund rule
                        <select name="cancellation_policy" class="input">
                            @foreach(['flexible' => 'Flexible', 'moderate' => 'Moderate', 'strict' => 'Strict'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('cancellation_policy', $listing->cancellation_policy ?? 'moderate') === $value)>{{ $label }}: {{ \App\Services\CancellationPolicy::summary($value) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="field-label">Cancellation policy text shown to guests
                        <textarea name="cancellation_policy_text" class="input" rows="4" maxlength="2000" placeholder="Leave blank to use the standard wording for the refund rule above.">{{ old('cancellation_policy_text', $listing->cancellation_policy_text) }}</textarea>
                        <span class="text-xs text-gray-500">Refunds are still calculated from the refund rule. This text is what guests read.</span>
                    </label>
                </div>
            </section>

            <section class="card card-pad">
                <h2 class="section-title">Manual payment methods</h2>
                <p class="section-copy">Payment methods belong to the property owner and are shared across that owner's listings.</p>
                @if($property->owner_id)
                    @php
                        $stripeOwner = $listing->property?->owner ?? auth()->user();
                        $isOwnerView = $stripeOwner->id === auth()->id();
                    @endphp
                    @if($stripeOwner->stripe_account_id && $stripeOwner->stripe_charges_enabled)
                        <p class="mt-3 flex items-center gap-2 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">
                            <span aria-hidden="true">✓</span>
                            <span>Stripe connected{{ $isOwnerView ? '' : ' for '.$stripeOwner->name }}. Card payments are enabled (account …{{ substr($stripeOwner->stripe_account_id, -6) }}).</span>
                        </p>
                    @elseif($stripeOwner->stripe_account_id)
                        <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">{{ $isOwnerView ? 'Your' : $stripeOwner->name."'s" }} Stripe account is linked, but setup isn't finished yet. Card payments are not available.</p>
                        @if($isOwnerView)
                            <button form="connect-form" class="btn-secondary mt-3" type="submit">Finish Stripe setup</button>
                        @else
                            <p class="mt-2 text-sm text-slate-500">The owner needs to finish setup from their own login.</p>
                        @endif
                    @else
                        @if($isOwnerView)
                            <button form="connect-form" class="btn-secondary" type="submit">Connect Stripe for card payments</button>
                        @else
                            <p class="mt-3 rounded-lg bg-slate-100 p-3 text-sm text-slate-700">Stripe is not connected for {{ $stripeOwner->name }}. Card payments are off until the owner connects from their own login.</p>
                        @endif
                    @endif
                @endif
                @if(! $property->owner_id)
                    <p class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">Assign an owner to this property before adding host payment methods.</p>
                @endif
                <div class="mt-4 grid gap-4">
                    @foreach($methodsValue as $index => $method)
                        <div class="grid gap-3 rounded-xl border border-slate-200 p-4 md:grid-cols-2">
                            <label class="field-label">Type
                                <select name="payment_methods[{{ $index }}][type]" class="input" @disabled(! $property->owner_id)>
                                    <option value="">Choose a type</option>
                                    @foreach(['zelle' => 'Zelle', 'venmo' => 'Venmo', 'paypal' => 'PayPal', 'cashapp' => 'Cash App', 'other' => 'Other'] as $value => $label)
                                        <option value="{{ $value }}" @selected(($method['type'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="field-label">Display label
                                <input name="payment_methods[{{ $index }}][label]" value="{{ $method['label'] ?? '' }}" class="input" maxlength="120" @disabled(! $property->owner_id)>
                            </label>
                            <label class="field-label">Handle or account detail
                                <input name="payment_methods[{{ $index }}][handle]" value="{{ $method['handle'] ?? '' }}" class="input" maxlength="255" @disabled(! $property->owner_id)>
                            </label>
                            <label class="field-label">Guest instructions
                                <textarea name="payment_methods[{{ $index }}][instructions]" rows="3" class="textarea" maxlength="5000" @disabled(! $property->owner_id)>{{ $method['instructions'] ?? '' }}</textarea>
                            </label>
                            <label class="flex items-center gap-2 text-sm font-medium md:col-span-2">
                                <input type="hidden" name="payment_methods[{{ $index }}][enabled]" value="0">
                                <input type="checkbox" name="payment_methods[{{ $index }}][enabled]" value="1" @checked((bool) ($method['enabled'] ?? true)) @disabled(! $property->owner_id)>
                                Enabled
                            </label>
                        </div>
                    @endforeach
                </div>
                @error('payment_methods')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
            </section>

            <section class="card card-pad">
                <h2 class="section-title">Lease applications</h2>
                @if($listing->exists && $listing->lease_enabled)
                    <a href="{{ route('admin.lease-applications.index') }}" class="mt-2 inline-flex text-sm font-semibold text-slate-700 underline">Review submitted applications</a>
                @endif
                <div class="mt-4 listing-responsive-grid grid gap-5 md:grid-cols-2">
                    <label class="flex items-center gap-2 text-sm font-medium md:col-span-2">
                        <input type="hidden" name="lease_enabled" value="0">
                        <input type="checkbox" name="lease_enabled" value="1" @checked(old('lease_enabled', $listing->lease_enabled))>
                        Accept long-term lease applications
                    </label>
                    <label class="field-label">Advertised stay type
                        <select name="listing_type" class="input">
                            <option value="both" @selected(old('listing_type', $listing->listing_type ?? 'both') === 'both')>Short-term and long-term</option>
                            <option value="short_term" @selected(old('listing_type', $listing->listing_type ?? 'both') === 'short_term')>Short-term only</option>
                            <option value="long_term" @selected(old('listing_type', $listing->listing_type ?? 'both') === 'long_term')>Long-term only</option>
                        </select>
                        <span class="text-xs text-slate-500">Long-term only hides the nightly booking form and directs guests to apply.</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium md:col-span-2">
                        <input type="hidden" name="lease_auto_route_29_nights" value="0">
                        <input type="checkbox" name="lease_auto_route_29_nights" value="1" @checked(old('lease_auto_route_29_nights', $listing->lease_auto_route_29_nights ?? true))>
                        Automatically send stays of 29 nights or more to the lease application
                    </label>
                    <label class="field-label">Application fee (USD)
                        <div class="flex items-center gap-2"><span class="text-slate-500">$</span><input inputmode="decimal" name="lease_application_fee" value="{{ old('lease_application_fee', $listing->lease_application_fee_cents !== null ? number_format($listing->lease_application_fee_cents / 100, 2, '.', '') : '') }}" class="input" placeholder="0.00"></div>
                        @error('lease_application_fee')<span class="field-help text-red-700">{{ $message }}</span>@enderror
                        <div class="mt-3 text-sm"><input type="hidden" name="lease_allow_passport" value="0"><label class="flex items-center gap-2"><input type="checkbox" name="lease_allow_passport" value="1" @checked(old('lease_allow_passport', $listing->lease_allow_passport))> Let applicants use a passport instead of a state ID</label></div>
                        <label class="field-label mt-3">Application fee refund policy
                            <select name="lease_fee_refund_policy" class="input">
                                @foreach(['unset' => 'Not set (applicants are told to ask the host)', 'non_refundable' => 'Non-refundable', 'refundable_if_declined' => 'Refunded if the application is declined'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('lease_fee_refund_policy', $listing->lease_fee_refund_policy ?? 'unset') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="text-xs text-gray-500">Shown to applicants before they pay. Card payments: Stripe usually keeps its own processing fee on a refund. Some states limit application fees, so check local rules.</span>
                        </label>
                    </label>
                </div>
            </section>

            <section class="card card-pad">
                <h2 class="section-title">Search preview</h2>
                <div class="mt-4 listing-responsive-grid grid gap-5 md:grid-cols-2">
                    <label class="field-label">SEO title
                        <input name="meta_title" value="{{ old('meta_title', $listing->meta_title) }}" maxlength="255" class="input">
                    </label>
                    <label class="field-label">SEO description
                        <textarea name="meta_description" rows="3" maxlength="320" class="textarea">{{ old('meta_description', $listing->meta_description) }}</textarea>
                    </label>
                </div>
                <div class="mt-5 rounded-xl bg-slate-50 p-4">
                    <h3 class="text-sm font-semibold text-slate-900">SEO checklist</h3>
                    <ul class="mt-3 grid gap-2 text-sm text-slate-600">
                        <li>Use a distinct, descriptive page title and meta description.</li>
                        <li>Describe the property in 150 or more useful words.</li>
                        <li>Add accurate alt text to photos and include at least five images.</li>
                        <li>Keep the property city and coordinates up to date; never add access codes or private entry details.</li>
                    </ul>
                </div>
            </section>
        </div>

        <aside class="card card-pad xl:sticky xl:top-5">
            <h2 class="section-title">Publishing checklist</h2>
            <p class="section-copy">Publishing is blocked until every required item is complete.</p>
            <ul class="mt-4 grid gap-3 text-sm">
                <li class="{{ filled(old('public_title', $listing->public_title)) ? 'text-emerald-700' : 'text-slate-500' }}">Title {{ filled(old('public_title', $listing->public_title)) ? '✓' : '—' }}</li>
                <li class="{{ $descriptionWords >= 150 ? 'text-emerald-700' : 'text-slate-500' }}">Description ({{ $descriptionWords }}/150 words) {{ $descriptionWords >= 150 ? '✓' : '—' }}</li>
                <li class="{{ $photoCount >= 5 ? 'text-emerald-700' : 'text-slate-500' }}">Five photos ({{ $photoCount }}/5) {{ $photoCount >= 5 ? '✓' : '—' }}</li>
                <li class="{{ $listing->base_rate_cents !== null ? 'text-emerald-700' : 'text-slate-500' }}">Base nightly rate {{ $listing->base_rate_cents !== null ? '✓' : '—' }}</li>
                <li class="{{ $listing->max_guests ? 'text-emerald-700' : 'text-slate-500' }}">Maximum guests {{ $listing->max_guests ? '✓' : '—' }}</li>
                <li class="{{ $property->latitude !== null && $property->longitude !== null ? 'text-emerald-700' : 'text-slate-500' }}">Property coordinates {{ $property->latitude !== null && $property->longitude !== null ? '✓' : '—' }}</li>
                <li class="{{ $paymentMethods->contains(fn ($method) => $method->enabled) || ($property->owner?->stripe_account_id && $property->owner?->stripe_charges_enabled) ? 'text-emerald-700' : 'text-slate-500' }}">Enabled payment method {{ $paymentMethods->contains(fn ($method) => $method->enabled) || ($property->owner?->stripe_account_id && $property->owner?->stripe_charges_enabled) ? '✓' : '—' }}</li>
            </ul>
            <div class="mt-6 border-t border-slate-200 pt-5">
                <label class="flex items-start gap-3 text-sm font-medium">
                    <input type="hidden" name="published" value="0">
                    <input type="checkbox" name="published" value="1" @checked(old('published', (bool) $listing->published_at))>
                    <span>Publish listing</span>
                </label>
                @if($listing->published_at)
                    <p class="mt-2 text-xs text-emerald-700">Published {{ $listing->published_at->format('M j, Y') }}. Unchecking will take it offline.</p>
                    @php
                        $embedUrl = route('public.listing.embed', $listing->listing_slug);
                        $embedCode = '<iframe src="'.$embedUrl.'" width="100%" height="760" style="border:0;max-width:100%;border-radius:12px" loading="lazy" title="'.e($listing->public_title).'"></iframe>';
                    @endphp
                    <div class="mt-5 border-t border-slate-200 pt-5">
                        <h3 class="text-sm font-semibold text-slate-900">Add booking to your own website</h3>
                        <p class="mt-1 text-xs text-slate-500">Embed this responsive booking form on your website. Guests can check dates, see the live total and continue to secure checkout on GuestHub.</p>
                        <textarea id="listing-embed-code" class="textarea mt-3 w-full font-mono text-xs" rows="5" readonly>{{ $embedCode }}</textarea>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button type="button" id="copy-listing-embed" class="btn-secondary">Copy embed code</button>
                            <a href="{{ $embedUrl }}" target="_blank" rel="noopener" class="btn-secondary">Preview widget</a>
                        </div>
                        <p id="listing-embed-copied" class="mt-2 text-xs text-emerald-700" role="status"></p>
                    </div>
                @else
                    <p class="mt-2 text-xs text-slate-500">Unpublished listings are not public.</p>
                @endif
                <button class="btn-primary mt-5 w-full">Save listing</button>
            </div>
        </aside>
    </form>
    <form id="connect-form" method="post" action="{{ route('admin.connect.onboard') }}">@csrf<input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}"></form>

    <script>
        (() => {
            const copyButton = document.getElementById('copy-listing-embed');
            copyButton?.addEventListener('click', async () => {
                const field = document.getElementById('listing-embed-code');
                const status = document.getElementById('listing-embed-copied');
                try {
                    await navigator.clipboard.writeText(field.value);
                } catch {
                    field.select();
                    document.execCommand('copy');
                }
                status.textContent = 'Embed code copied.';
            });

            const list = document.getElementById('listing-photo-sort');
            if (!list) return;
            let dragged = null;
            list.addEventListener('dragstart', event => {
                dragged = event.target.closest('.listing-photo-card');
                if (dragged) event.dataTransfer.effectAllowed = 'move';
            });
            list.addEventListener('dragover', event => {
                event.preventDefault();
                const target = event.target.closest('.listing-photo-card');
                if (!dragged || !target || target === dragged) return;
                const bounds = target.getBoundingClientRect();
                const after = event.clientY > bounds.top + bounds.height / 2;
                list.insertBefore(dragged, after ? target.nextSibling : target);
            });
            list.addEventListener('dragend', () => { dragged = null; });
        })();
    </script>
</x-admin-layout>
