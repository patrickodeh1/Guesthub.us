@extends('layouts.public')

@section('title', $metaTitle)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
    <meta property="og:image" content="{{ $public['photos'][0]['url'] ?? '' }}">
    <meta name="twitter:card" content="summary_large_image">
    <script type="application/ld+json">@json($structuredData)</script>
@endpush

@section('content')
    <div class="eyebrow">A direct-booking stay</div>
    <h1>{{ $listing->public_title }}</h1>
    <p class="lede">{{ $public['city'] }}{{ $public['state'] ? ', '.$public['state'] : '' }} · Hosted by <a href="{{ $public['host_url'] }}">{{ $public['host_name'] }}</a></p>

    <div class="gallery" aria-label="Listing photo gallery">
        @foreach($public['photos'] as $photo)
            <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif>
        @endforeach
    </div>

    <div class="detail-grid">
        <article>
            <div class="specs">
                <span class="spec">{{ $public['bedrooms'] }} bedrooms</span>
                @if($public['baths'] !== null)<span class="spec">{{ $public['baths'] }} baths</span>@endif
                <span class="spec">Up to {{ $listing->max_guests }} guests</span>
                <span class="spec">Minimum {{ $listing->min_nights }} {{ Str::plural('night', $listing->min_nights) }}</span>
            </div>
            <section class="detail-copy">
                <h2>About this stay</h2>
                <p>{!! nl2br(e($listing->public_description)) !!}</p>
            </section>
            @if($public['amenities'])
                <section>
                    <h2>What this place offers</h2>
                    <div class="specs">@foreach($public['amenities'] as $amenity)<span class="spec">{{ $amenity }}</span>@endforeach</div>
                </section>
            @endif
            @if($listing->house_rules)
                <section class="detail-copy"><h2>House rules</h2><p>{!! nl2br(e($listing->house_rules)) !!}</p></section>
            @endif
            <section class="detail-copy">
                <h2>Cancellation policy</h2>
                <p>{{ $public['cancellation_policy'] }}</p>
            </section>
            <section>
                <h2>Location</h2>
                <p class="muted">The exact address is shared with confirmed guests. This map shows an approximate location in {{ $public['city'] }}.</p>
                <iframe title="Approximate map location in {{ $public['city'] }}" width="100%" height="300" loading="lazy" referrerpolicy="no-referrer-when-downgrade" style="border:0;border-radius:14px" src="https://www.openstreetmap.org/export/embed.html?bbox={{ $public['longitude'] - 0.02 }}%2C{{ $public['latitude'] - 0.015 }}%2C{{ $public['longitude'] + 0.02 }}%2C{{ $public['latitude'] + 0.015 }}&layer=mapnik&marker={{ $public['latitude'] }}%2C{{ $public['longitude'] }}"></iframe>
            </section>
            @if($listing->lease_enabled && \App\Services\FeatureGate::enabled($listing->property->owner, 'lease_applications'))
                <section id="lease-application" class="panel" style="margin-top:24px">
                    <h2>Interested in a longer stay?</h2>
                    @if($listing->screening_provider_url)
                        <p class="muted">Submit a short application; the host may request screening through their provider. <a href="{{ $listing->screening_provider_url }}" target="_blank" rel="noopener noreferrer">Screening provider</a></p>
                    @endif
                    <a class="button" href="{{ route('public.lease-application.apply', $listing->listing_slug) }}">Apply for a long-term lease</a>
                </section>
            @endif
        </article>

        <aside class="detail-aside">
            <section class="panel">
                <div class="price" style="font-size:1.35rem">${{ number_format($listing->base_rate_cents / 100, 2) }} <span class="muted">/ night</span></div>
                <form id="quote-form" class="search-grid" style="grid-template-columns:1fr;margin-top:16px">
                    <div class="field"><label for="quote-check-in">Check-in</label><input required id="quote-check-in" name="check_in" type="date" min="{{ now($public['timezone'])->toDateString() }}"></div>
                    <div class="field"><label for="quote-check-out">Check-out</label><input required id="quote-check-out" name="check_out" type="date"></div>
                    <div class="field"><label for="quote-guests">Guests</label><input required id="quote-guests" name="guests" type="number" min="1" max="{{ $listing->max_guests }}" value="1"></div>
                    @if($public['payment_options']['card'] || $public['payment_options']['manual'])
                        <div class="field"><label for="quote-payment-method">Payment type</label><select id="quote-payment-method" name="payment_method">
                            @if($public['payment_options']['card'])<option value="card" @selected(!$public['payment_options']['manual'])>Card</option>@endif
                            @if($public['payment_options']['manual'])<option value="manual" @selected(!$public['payment_options']['card'])>Manual payment</option>@endif
                        </select></div>
                        <button class="button" type="submit">Get price estimate</button>
                    @else
                        <p class="muted">Booking options are temporarily unavailable for this stay.</p>
                    @endif
                </form>
                <div id="quote-error" class="status-message" role="status" aria-live="polite"></div>
                <div id="quote-result" hidden>
                    <div id="quote-lines" class="quote-lines"></div>
                    <div class="quote-line quote-total"><span>Estimated total</span><span id="quote-total"></span></div>
                    <a id="checkout-link" class="button" href="{{ route('public.checkout.show', $listing->listing_slug) }}">Continue to checkout</a>
                </div>
            </section>

            <section class="panel" style="margin-top:18px">
                <h2>Availability</h2>
                <p class="muted">Dates shown in grey are unavailable. Minimum-stay rules may apply.</p>
                <div class="calendar-grid">
                    @foreach(['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $weekday)
                        <div class="calendar-day" aria-hidden="true"><strong>{{ $weekday }}</strong></div>
                    @endforeach
                    @if(count($calendar))
                        @for($padding = 0; $padding < \Carbon\Carbon::parse(array_key_first($calendar))->dayOfWeek; $padding++)
                            <div aria-hidden="true"></div>
                        @endfor
                    @endif
                    @foreach($calendar as $date => $day)
                        <div class="calendar-day {{ $day['available'] ? '' : 'blocked' }}" title="{{ $day['available'] ? 'Available' : 'Unavailable' }} · Minimum {{ $day['minimum_nights'] }} nights">
                            {{ \Carbon\Carbon::parse($date)->format('M') }}<small>{{ \Carbon\Carbon::parse($date)->format('j') }}</small>
                        </div>
                    @endforeach
                </div>
                <div class="muted">Rates are subject to change until checkout is available.</div>
            </section>
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('quote-form');
            const error = document.getElementById('quote-error');
            const result = document.getElementById('quote-result');
            const lines = document.getElementById('quote-lines');
            const currency = new Intl.NumberFormat(undefined, {style: 'currency', currency: 'USD'});
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                error.textContent = '';
                result.hidden = true;
                const query = new URLSearchParams(new FormData(form));
                try {
                    const response = await fetch(@json($quoteUrl) + '?' + query.toString(), {headers: {'Accept': 'application/json'}});
                    const data = await response.json();
                    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || 'Unable to quote these dates.');
                    lines.replaceChildren();
                    data.line_items.forEach(function (item) {
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
                    const checkout = document.getElementById('checkout-link');
                    const checkIn = document.getElementById('quote-check-in').value;
                    const checkOut = document.getElementById('quote-check-out').value;
                    const guests = document.getElementById('quote-guests').value;
                    checkout.href = @json(route('public.checkout.show', $listing->listing_slug))
                        + '?check_in=' + encodeURIComponent(checkIn)
                        + '&check_out=' + encodeURIComponent(checkOut)
                        + '&guests=' + encodeURIComponent(guests);
                    result.hidden = false;
                } catch (exception) {
                    error.textContent = exception.message;
                }
            });
        })();
    </script>
@endpush
