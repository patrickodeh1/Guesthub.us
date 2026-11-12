@extends('layouts.public')

@section('title', $metaTitle)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
    <meta property="og:image" content="{{ $public['photos'][0]['url'] ?? '' }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        .stay-heading{margin-bottom:16px}
        .stay-title{font-size:clamp(1.5rem,3vw,2rem);font-weight:650}
        .stay-meta{gap:6px 16px}
        .stay-gallery{border-radius:16px;overflow:hidden}
        .stay-section{padding:32px 0;border-bottom:1px solid #e1e7e2}
        .stay-section h2{font-size:1.35rem;margin:0 0 16px}
        .stay-highlights{list-style:none;margin:0;padding:24px 0;border-bottom:1px solid #e1e7e2;display:grid;gap:20px}
        .stay-highlights li{display:flex;gap:16px;align-items:flex-start}
        .stay-highlights svg{flex:none;width:26px;height:26px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round;margin-top:2px}
        .stay-highlights strong{display:block;font-weight:650}
        .stay-highlights span{color:#66746d;font-size:.9rem}
        .booking-card{border:1px solid #dfe6e1;border-radius:16px;box-shadow:0 6px 24px #17221e1f;padding:24px}
        .booking-price h2{font-size:1.6rem;margin:0 0 16px}
        .booking-price h2 span{font-size:1rem;font-weight:400;color:#66746d}
        .location-map{height:380px;border-radius:16px;overflow:hidden;background:#e7ece8;z-index:0}
        .mobile-booking-bar{display:none!important}
        .stay-page{padding-bottom:0}
    </style>
    <script type="application/ld+json">@json($structuredData)</script>
@endpush

@section('content')
    @php
        $photos = $public['photos'] ?? [];
        $photoCount = count($photos);
        $amenities = array_values($public['amenities'] ?? []);
        $amenityCount = count($amenities);
        $today = \Carbon\Carbon::now($public['timezone'])->startOfDay();
        $descriptionIsLong = strlen(strip_tags((string) $listing->public_description)) > 560;
    @endphp

    <div class="stay-page">
        <header class="stay-heading">
            <h1 class="stay-title">{{ $listing->public_title }}</h1>
            <ul class="stay-meta" aria-label="Stay details">
                <li>
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                    <span>{{ $public['city'] }}{{ $public['state'] ? ', '.$public['state'] : '' }}</span>
                </li>
                <li>
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 19V8m18 11V8M3 14h18M6 14V9a3 3 0 0 1 6 0v5m0-3h6a3 3 0 0 1 3 3"/></svg>
                    <span>{{ $public['bedrooms'] }} bedrooms</span>
                </li>
                <li>
                    <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1Zm14-9a3 3 0 1 0-1-5.8M18 14a5 5 0 0 1 3 4.6V20h-3"/></svg>
                    <span>Up to {{ $listing->max_guests }} guests</span>
                </li>
                <li>
                    <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01"/></svg>
                    <span>Minimum {{ $listing->min_nights }} {{ Str::plural('night', $listing->min_nights) }}</span>
                </li>
            </ul>
        </header>

        @if($photoCount)
            <section class="stay-gallery photos-{{ min($photoCount, 5) }}" aria-label="Photos of {{ $listing->public_title }}" data-gallery-count="{{ $photoCount }}">
                @foreach($photos as $photo)
                    <button
                        type="button"
                        class="photo-trigger"
                        data-photo-index="{{ $loop->index }}"
                        data-photo-url="{{ $photo['url'] }}"
                        data-photo-alt="{{ $photo['alt'] }}"
                        data-photo-width="{{ $photo['width'] }}"
                        data-photo-height="{{ $photo['height'] }}"
                        aria-label="View photo {{ $loop->iteration }} of {{ $photoCount }}: {{ $photo['alt'] }}"
                    >
                        <img
                            src="{{ $photo['url'] }}"
                            alt="{{ $photo['alt'] }}"
                            width="{{ $photo['width'] }}"
                            height="{{ $photo['height'] }}"
                            @if($loop->first) fetchpriority="high" @else loading="lazy" @endif
                        >
                    </button>
                @endforeach
                @if($photoCount > 1)
                    <button type="button" class="show-all-photos" data-open-photo-dialog aria-label="Show all {{ $photoCount }} photos">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                        Show all photos
                    </button>
                @endif
                <span class="gallery-counter" aria-live="polite"><span data-gallery-current>1</span> / {{ $photoCount }}</span>
            </section>
            @if($photoCount > 1)
                <dialog class="photo-dialog" id="photo-dialog" aria-label="Photo viewer">
                    <div class="dialog-toolbar">
                        <span data-photo-dialog-counter aria-live="polite">1 / {{ $photoCount }}</span>
                        <button type="button" class="icon-button" data-photo-close aria-label="Close photo viewer">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg>
                        </button>
                    </div>
                    <div class="lightbox-content">
                        <button type="button" class="icon-button lightbox-arrow" data-photo-prev aria-label="Previous photo">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <figure class="lightbox-figure">
                            <img data-photo-dialog-image alt="" width="1200" height="800">
                            <figcaption data-photo-dialog-caption></figcaption>
                        </figure>
                        <button type="button" class="icon-button lightbox-arrow" data-photo-next aria-label="Next photo">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                    </div>
                </dialog>
            @endif
        @endif

        <nav class="section-nav" aria-label="Stay sections">
            <a href="#overview" data-section-link="overview">Overview</a>
            @if($amenityCount)
                <a href="#amenities" data-section-link="amenities">Amenities</a>
            @endif
            <a href="#location" data-section-link="location">Location</a>
            <a href="#policies" data-section-link="policies">Policies</a>
        </nav>

        <div class="stay-layout">
            <article class="stay-content">
                <ul class="stay-highlights" aria-label="Highlights">
                    <li>
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg>
                        <div><strong>{{ $listing->booking_mode === 'request' ? 'Request to book' : 'Book directly' }}</strong><span>{{ $listing->booking_mode === 'request' ? 'The host reviews and approves each request.' : 'Check availability and book with no middleman.' }}</span></div>
                    </li>
                    <li>
                        <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <div><strong>Minimum {{ $listing->min_nights }} {{ Str::plural('night', $listing->min_nights) }}</strong><span>Up to {{ $listing->max_guests }} guests</span></div>
                    </li>
                    <li>
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <div><strong>Cancellation policy</strong><span>{{ $public['cancellation_policy'] }}</span></div>
                    </li>
                </ul>
                <section id="overview" class="stay-section">
                    <h2>About this stay</h2>
                    <div class="stay-description {{ $descriptionIsLong ? 'description-long' : '' }}" data-description>
                        <p>{!! nl2br(e($listing->public_description)) !!}</p>
                    </div>
                    @if($descriptionIsLong)
                        <button type="button" class="text-action" data-description-toggle hidden aria-expanded="false">Show more</button>
                    @endif
                </section>

                @if($amenityCount)
                    <section id="amenities" class="stay-section">
                        <h2>What this place offers</h2>
                        <ul class="amenity-list">
                            @foreach(array_slice($amenities, 0, 10) as $amenity)
                                @php
                                    $amenityName = strtolower($amenity);
                                    $amenityIcon = str_contains($amenityName, 'wi-fi') || str_contains($amenityName, 'wifi') ? 'wifi'
                                        : (str_contains($amenityName, 'parking') ? 'parking'
                                            : (str_contains($amenityName, 'kitchen') ? 'kitchen'
                                                : (str_contains($amenityName, 'washer') || str_contains($amenityName, 'laundry') ? 'washer'
                                                    : (str_contains($amenityName, 'air conditioning') || preg_match('/\bac\b/', $amenityName) ? 'air'
                                                        : (str_contains($amenityName, 'pool') ? 'pool' : 'check')))));
                                @endphp
                                <li class="amenity-item">
                                    <svg aria-hidden="true" viewBox="0 0 24 24">
                                        @if($amenityIcon === 'wifi')
                                            <path d="M2 8.8a15 15 0 0 1 20 0M5 12a10.5 10.5 0 0 1 14 0m-11 3.2a5.5 5.5 0 0 1 8 0M12 19h.01"/>
                                        @elseif($amenityIcon === 'parking')
                                            <path d="M6 21V3h7a6 6 0 0 1 0 12h-7m0-6h7"/>
                                        @elseif($amenityIcon === 'kitchen')
                                            <path d="M4 3v7m4-7v7M6 10v11m12-18v18m0-18a4 4 0 0 0-4 4v5h4"/>
                                        @elseif($amenityIcon === 'washer')
                                            <rect x="4" y="3" width="16" height="18" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 6h.01M12 6h.01M16 6h.01"/>
                                        @elseif($amenityIcon === 'air')
                                            <path d="M3 8h12a3 3 0 1 0-3-3M2 12h17a3 3 0 1 1-3 3M4 16h8a3 3 0 1 1-3 3"/>
                                        @elseif($amenityIcon === 'pool')
                                            <path d="M3 17c2 0 2-2 4-2s2 2 5 2 3-2 5-2 2 2 4 2M3 21c2 0 2-2 4-2s2 2 5 2 3-2 5-2 2 2 4 2M6 13V4m0 0h7a3 3 0 0 1 0 6H6"/>
                                        @else
                                            <path d="m5 12 4 4L19 6"/>
                                        @endif
                                    </svg>
                                    <span>{{ $amenity }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if($amenityCount > 10)
                            <button type="button" class="secondary-button" data-open-amenities>Show all {{ $amenityCount }} amenities</button>
                            <dialog class="amenities-dialog" id="amenities-dialog" aria-labelledby="amenities-dialog-title">
                                <div class="dialog-toolbar">
                                    <h2 id="amenities-dialog-title">All amenities</h2>
                                    <button type="button" class="icon-button" data-amenities-close aria-label="Close amenities">
                                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m6 6 12 12M18 6 6 18"/></svg>
                                    </button>
                                </div>
                                <ul class="amenity-list amenities-dialog-list">
                                    @foreach($amenities as $amenity)
                                        <li class="amenity-item"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg><span>{{ $amenity }}</span></li>
                                    @endforeach
                                </ul>
                            </dialog>
                        @endif
                    </section>
                @endif

                <section id="location" class="stay-section">
                    <h2>Explore {{ $public['city'] }}</h2>
                    <p class="section-copy">The shaded area is approximate. The exact address is shared after booking.</p>
                    <div id="location-map" class="location-map" role="img" aria-label="Approximate map of {{ $public['city'] }}" data-lat="{{ $public['latitude'] }}" data-lng="{{ $public['longitude'] }}"></div>
                </section>

                <section id="policies" class="stay-section">
                    <h2>Policies</h2>
                    @if($listing->house_rules)
                        <div class="policy-block">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg>
                            <div><h3>House rules</h3><p>{!! nl2br(e($listing->house_rules)) !!}</p></div>
                        </div>
                    @endif
                    <div class="policy-block">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        <div><h3>Cancellation policy</h3><p>{{ $public['cancellation_policy'] }}</p></div>
                    </div>
                </section>

                @if($listing->lease_enabled && ($listing->listing_type ?? 'both') !== 'short_term' && \App\Services\FeatureGate::enabled($listing->property->owner, 'lease_applications'))
                    <section id="lease-application" class="lease-card">
                        <h2>Interested in a longer stay?</h2>
                        @if($listing->screening_provider_url)
                            <p>Submit a short application; the host may request screening through their provider. <a href="{{ $listing->screening_provider_url }}" target="_blank" rel="noopener noreferrer">Screening provider details</a></p>
                        @endif
                        <a class="secondary-button" href="{{ route('public.lease-application.apply', $listing->listing_slug) }}">Apply for a long-term lease</a>
                    </section>
                @endif
            </article>

            @if(($listing->listing_type ?? 'both') !== 'long_term')
            <aside class="booking-column" aria-label="Book this stay">
                <section class="booking-card" id="booking-card" aria-labelledby="booking-card-title">
                    <div class="booking-price">
                        @php($fromPrice = app(\App\Http\Controllers\PublicListingController::class)->fromPrice($listing))
                        <h2 id="booking-card-title">{{ $fromPrice['varies'] ? 'From ' : '' }}${{ number_format($fromPrice['cents'] / 100, 2) }} <span>/ night</span></h2>
                        @if ($fromPrice['varies'])<p class="muted" style="margin:-8px 0 12px;font-size:.85rem">Prices vary by date. Choose your dates for an exact total.</p>@endif
                    </div>
                    <form id="quote-form" class="booking-form">
                        <div class="date-control">
                            <label for="quote-check-in"><span>Check-in</span><input required id="quote-check-in" name="check_in" type="date" min="{{ $today->toDateString() }}" aria-label="Check-in date"></label>
                            <span class="date-divider" aria-hidden="true"></span>
                            <label for="quote-check-out"><span>Check-out</span><input required id="quote-check-out" name="check_out" type="date" min="{{ $today->toDateString() }}" aria-label="Check-out date"></label>
                        </div>
                        <label class="booking-field" for="quote-guests">
                            <span>Guests</span>
                            <input required id="quote-guests" name="guests" type="number" min="1" max="{{ $listing->max_guests }}" value="1">
                        </label>
                        @if($public['payment_options']['card'] || $public['payment_options']['manual'])
                            <button class="primary-button" id="quote-submit" type="submit">
                                <span data-submit-label>Check availability</span>
                                <svg class="button-spinner" data-submit-spinner aria-hidden="true" hidden viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/></svg>
                            </button>
                        @else
                            <p class="section-copy">Booking options are temporarily unavailable for this stay.</p>
                        @endif
                    </form>
                    <p id="quote-error" class="quote-error" role="status" aria-live="polite"></p>
                    <div id="quote-result" class="quote-result" hidden>
                        <div id="quote-lines" class="quote-breakdown"></div>
                        <div class="quote-total"><span>Total</span><strong id="quote-total"></strong></div>
                        <p class="charge-note">You won't be charged yet.</p>
                        <a id="checkout-link" class="primary-button" href="{{ route('public.checkout.show', $listing->listing_slug) }}">Continue to checkout</a>
                    </div>
                </section>
                <p class="booking-reassurance">Your price is calculated from current rates and fees. Dates are not reserved until checkout.</p>
            </aside>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        (() => {
            const page = document.querySelector('.stay-page');
            if (!page) return;

            const photoButtons = [...page.querySelectorAll('.photo-trigger')];
            const photoDialog = document.getElementById('photo-dialog');
            const photoDialogImage = photoDialog?.querySelector('[data-photo-dialog-image]');
            const photoDialogCaption = photoDialog?.querySelector('[data-photo-dialog-caption]');
            const photoDialogCounter = photoDialog?.querySelector('[data-photo-dialog-counter]');
            let activePhoto = 0;
            let dialogOpener = null;
            let touchStartX = null;

            const focusableIn = (dialog) => [...dialog.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])')];
            const showDialog = (dialog, opener) => {
                if (!dialog || typeof dialog.showModal !== 'function') return;
                dialogOpener = opener;
                dialog.showModal();
                focusableIn(dialog)[0]?.focus();
            };
            document.querySelectorAll('dialog').forEach((dialog) => {
                dialog.addEventListener('close', () => {
                    if (dialogOpener?.isConnected) dialogOpener.focus();
                    dialogOpener = null;
                });
                dialog.addEventListener('keydown', (event) => {
                    if (event.key !== 'Tab') return;
                    const focusable = focusableIn(dialog);
                    if (!focusable.length) return;
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                });
            });

            const displayPhoto = (index) => {
                if (!photoButtons.length || !photoDialogImage) return;
                activePhoto = (index + photoButtons.length) % photoButtons.length;
                const trigger = photoButtons[activePhoto];
                photoDialogImage.src = trigger.dataset.photoUrl;
                photoDialogImage.alt = trigger.dataset.photoAlt || '';
                photoDialogImage.width = Number(trigger.dataset.photoWidth) || 1200;
                photoDialogImage.height = Number(trigger.dataset.photoHeight) || 800;
                photoDialogCaption.textContent = trigger.dataset.photoAlt || '';
                photoDialogCounter.textContent = `${activePhoto + 1} / ${photoButtons.length}`;
            };
            const openPhoto = (index, opener) => {
                if (!photoDialog) return;
                displayPhoto(index);
                showDialog(photoDialog, opener);
            };
            photoButtons.forEach((button) => button.addEventListener('click', () => openPhoto(Number(button.dataset.photoIndex), button)));
            page.querySelector('[data-open-photo-dialog]')?.addEventListener('click', (event) => openPhoto(0, event.currentTarget));
            photoDialog?.querySelector('[data-photo-close]')?.addEventListener('click', () => photoDialog.close());
            photoDialog?.querySelector('[data-photo-prev]')?.addEventListener('click', () => displayPhoto(activePhoto - 1));
            photoDialog?.querySelector('[data-photo-next]')?.addEventListener('click', () => displayPhoto(activePhoto + 1));
            photoDialog?.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowRight') displayPhoto(activePhoto + 1);
                if (event.key === 'ArrowLeft') displayPhoto(activePhoto - 1);
            });
            photoDialog?.addEventListener('touchstart', (event) => { touchStartX = event.changedTouches[0].screenX; }, {passive: true});
            photoDialog?.addEventListener('touchend', (event) => {
                if (touchStartX === null) return;
                const delta = event.changedTouches[0].screenX - touchStartX;
                if (Math.abs(delta) > 48) displayPhoto(activePhoto + (delta < 0 ? 1 : -1));
                touchStartX = null;
            }, {passive: true});

            const amenitiesDialog = document.getElementById('amenities-dialog');
            page.querySelector('[data-open-amenities]')?.addEventListener('click', (event) => showDialog(amenitiesDialog, event.currentTarget));
            amenitiesDialog?.querySelector('[data-amenities-close]')?.addEventListener('click', () => amenitiesDialog.close());

            const description = page.querySelector('[data-description].description-long');
            const descriptionToggle = page.querySelector('[data-description-toggle]');
            if (description && descriptionToggle) {
                description.classList.add('is-collapsed');
                descriptionToggle.hidden = false;
                descriptionToggle.addEventListener('click', () => {
                    const expanded = description.classList.toggle('is-expanded');
                    description.classList.toggle('is-collapsed', !expanded);
                    descriptionToggle.textContent = expanded ? 'Show less' : 'Show more';
                    descriptionToggle.setAttribute('aria-expanded', String(expanded));
                });
            }

            const navLinks = [...page.querySelectorAll('[data-section-link]')];
            if ('IntersectionObserver' in window && navLinks.length) {
                const observer = new IntersectionObserver((entries) => {
                    const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
                    if (!visible) return;
                    navLinks.forEach((link) => {
                        const active = link.dataset.sectionLink === visible.target.id;
                        link.classList.toggle('is-active', active);
                        if (active) link.setAttribute('aria-current', 'location');
                        else link.removeAttribute('aria-current');
                    });
                }, {rootMargin: '-96px 0px -65% 0px', threshold: [0, 0.2, 0.5]});
                navLinks.forEach((link) => {
                    const section = document.getElementById(link.dataset.sectionLink);
                    if (section) observer.observe(section);
                });
            }

            const gallery = page.querySelector('.stay-gallery');
            const galleryCounter = gallery?.querySelector('[data-gallery-current]');
            let galleryFrame = null;
            gallery?.addEventListener('scroll', () => {
                if (!galleryCounter || galleryFrame) return;
                galleryFrame = requestAnimationFrame(() => {
                    const center = gallery.getBoundingClientRect().left + gallery.clientWidth / 2;
                    const closest = photoButtons.reduce((best, button, index) => {
                        const rect = button.getBoundingClientRect();
                        const distance = Math.abs(rect.left + rect.width / 2 - center);
                        return distance < best.distance ? {index, distance} : best;
                    }, {index: 0, distance: Infinity});
                    galleryCounter.textContent = String(closest.index + 1);
                    galleryFrame = null;
                });
            }, {passive: true});

            const checkIn = document.getElementById('quote-check-in');
            const checkOut = document.getElementById('quote-check-out');
            const mapEl = document.getElementById('location-map');
            if (mapEl) {
                window.addEventListener('load', () => {
                    if (!window.L) { mapEl.textContent = 'Map unavailable right now.'; return; }
                    const lat = Number(mapEl.dataset.lat), lng = Number(mapEl.dataset.lng);
                    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
                    const map = L.map(mapEl, {scrollWheelZoom: false}).setView([lat, lng], 13);
                    const esri = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {maxZoom: 19, attribution: 'Tiles &copy; Esri'});
                    const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'});
                    let fellBack = false;
                    esri.on('tileerror', () => { if (fellBack) return; fellBack = true; map.removeLayer(esri); osm.addTo(map); });
                    esri.addTo(map);
                    L.circle([lat, lng], {radius: 900, color: '#1f6f54', fillColor: '#1f6f54', fillOpacity: 0.18, weight: 2}).addTo(map);
                    setTimeout(() => map.invalidateSize(), 200);
                });
            }

            page.querySelector('[data-scroll-booking]')?.addEventListener('click', () => {
                const bookingCard = document.getElementById('booking-card');
                bookingCard?.scrollIntoView({behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center'});
                checkIn?.focus({preventScroll: true});
            });

            const form = document.getElementById('quote-form');
            const error = document.getElementById('quote-error');
            const result = document.getElementById('quote-result');
            const lines = document.getElementById('quote-lines');
            const submitButton = document.getElementById('quote-submit');
            const submitLabel = submitButton?.querySelector('[data-submit-label]');
            const submitSpinner = submitButton?.querySelector('[data-submit-spinner]');
            const currency = new Intl.NumberFormat(undefined, {style: 'currency', currency: 'USD'});
            form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!submitButton || !checkIn?.value || !checkOut?.value) {
                    error.textContent = 'Enter both check-in and check-out dates to see a price.';
                    return;
                }
                error.textContent = '';
                result.hidden = true;
                submitButton.disabled = true;
                submitButton.setAttribute('aria-busy', 'true');
                submitLabel.textContent = 'Checking dates…';
                submitSpinner.hidden = false;
                try {
                    const query = new URLSearchParams(new FormData(form));
                    const response = await fetch(@json($quoteUrl) + '?' + query.toString(), {headers: {'Accept': 'application/json'}});
                    if (!response.ok) {
                        error.textContent = response.status === 422
                            ? 'Those dates are unavailable or do not meet this stay’s booking requirements. Try other dates.'
                            : 'We could not check those dates right now. Please try again.';
                        return;
                    }
                    const data = await response.json();
                    lines.replaceChildren();
                    data.line_items.forEach((item) => {
                        const row = document.createElement('div');
                        row.className = 'quote-line';
                        const label = document.createElement('span');
                        label.textContent = item.label;
                        const amount = document.createElement('span');
                        amount.textContent = currency.format(item.amount_cents / 100);
                        row.append(label, amount);
                        lines.append(row);
                    });
                    document.getElementById('quote-total').textContent = currency.format(data.total_cents / 100);
                    const guests = document.getElementById('quote-guests').value;
                    document.getElementById('checkout-link').href = @json(route('public.checkout.show', $listing->listing_slug))
                        + '?check_in=' + encodeURIComponent(checkIn.value)
                        + '&check_out=' + encodeURIComponent(checkOut.value)
                        + '&guests=' + encodeURIComponent(guests);
                    result.hidden = false;
                } catch {
                    error.textContent = 'We could not check those dates right now. Check your connection and try again.';
                } finally {
                    submitButton.disabled = false;
                    submitButton.removeAttribute('aria-busy');
                    submitLabel.textContent = 'Check availability';
                    submitSpinner.hidden = true;
                }
            });
        })();
    </script>
@endpush
