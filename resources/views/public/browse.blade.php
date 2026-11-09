@extends('layouts.public')

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
    <script type="application/ld+json">@json($structuredData)</script>
    <meta property="og:image" content="{{ $listings->first()['photos'][0]['url'] ?? '' }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    <div class="eyebrow">Direct stays, thoughtfully hosted</div>
    <h1>{{ $title }}</h1>
    <p class="lede">{{ $intro }}</p>

    <form class="search-panel" method="get" action="{{ route('public.stays.index') }}">
        <div class="search-grid">
            <div class="field"><label for="city">City</label><input id="city" name="city" value="{{ $filters['city'] ?? '' }}" placeholder="Any city"></div>
            <div class="field"><label for="check_in">Check-in</label><input id="check_in" type="date" name="check_in" value="{{ $filters['check_in'] ?? '' }}"></div>
            <div class="field"><label for="check_out">Check-out</label><input id="check_out" type="date" name="check_out" value="{{ $filters['check_out'] ?? '' }}"></div>
            <div class="field"><label for="guests">Guests</label><input id="guests" type="number" min="1" max="100" name="guests" value="{{ $filters['guests'] ?? 1 }}"></div>
            <div class="field"><label for="price_min">Min nightly price</label><input id="price_min" type="number" min="0" step="1" name="price_min" value="{{ $filters['price_min'] ?? '' }}" placeholder="$"></div>
            <div class="field"><label for="price_max">Max nightly price</label><input id="price_max" type="number" min="0" step="1" name="price_max" value="{{ $filters['price_max'] ?? '' }}" placeholder="$"></div>
            <button class="button" type="submit">Search stays</button>
        </div>
    </form>

    <div class="split-heading">
        <div><h2>{{ $listings->total() }} {{ Str::plural('stay', $listings->total()) }}</h2><div class="muted">Locations on the map are approximate.</div></div>
    </div>
    @if($listings->isEmpty())
        <div class="empty">No published stays match those filters yet. Try another city or date range.</div>
    @else
        <div class="results-layout">
            <section aria-label="Stay listings">
                <div class="listing-grid">
                    @foreach($listings as $listing)
                        <article class="listing-card">
                            <a href="{{ $listing['url'] }}">
                                @if(!empty($listing['photos'][0]))
                                    <img src="{{ $listing['photos'][0]['thumbnail_url'] }}" alt="{{ $listing['photos'][0]['alt'] }}" width="{{ $listing['photos'][0]['width'] }}" height="{{ $listing['photos'][0]['height'] }}" loading="lazy">
                                @else
                                    <div style="aspect-ratio:3/2;background:#e6ebe7"></div>
                                @endif
                                <div class="card-body">
                                    <h2>{{ $listing['title'] }}</h2>
                                    <div class="muted">{{ $listing['city'] }}{{ $listing['state'] ? ', '.$listing['state'] : '' }}</div>
                                    <div class="muted">{{ $listing['bedrooms'] }} bedrooms · Up to {{ $listing['max_guests'] }} guests</div>
                                    <div class="price">${{ number_format($listing['base_rate_cents'] / 100, 2) }} <span class="muted">/ night</span></div>
                                </div>
                            </a>
                        </article>
                    @endforeach
                </div>
                <div class="pagination">{{ $listings->links() }}</div>
            </section>

            <aside class="panel map-panel">
                <h2 class="map-title">Approximate stay locations</h2>
                <div id="stay-map" class="map-canvas" role="img" aria-label="Map showing approximate listing locations"></div>
                <div class="map-note">Pins are intentionally generalized and do not show exact property addresses.</div>
            </aside>
        </div>
    @endif
@endsection

@push('scripts')
    <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        window.addEventListener('load', function () {
            if (!window.L || !document.getElementById('stay-map')) return;
            const stays = @json($mapPoints);
            if (!stays.length) return;
            const map = L.map('stay-map', {scrollWheelZoom: false}).setView([stays[0].latitude, stays[0].longitude], stays.length > 1 ? 5 : 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 18,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            const markers = [];
            stays.forEach(function (stay) {
                const marker = L.marker([stay.latitude, stay.longitude]).addTo(map);
                const popup = document.createElement('div');
                const link = document.createElement('a');
                link.href = stay.url;
                link.textContent = stay.title;
                const city = document.createElement('div');
                city.textContent = stay.city;
                popup.append(link, city);
                marker.bindPopup(popup);
                markers.push(marker);
            });
            if (markers.length > 1) map.fitBounds(L.featureGroup(markers).getBounds().pad(0.15));
        });
    </script>
@endpush
