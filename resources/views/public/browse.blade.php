@extends('layouts.public')

@php
    $showStayDiscovery = request()->routeIs('public.stays.index', 'public.stays.city');
    $cityGroups = $listings->getCollection()
        ->groupBy('city')
        ->sortByDesc(fn ($cityListings) => $cityListings->count());
@endphp

@section('title', $title)
@section('meta_description', $metaDescription)
@section('canonical', $canonical)

@push('head')
    <script type="application/ld+json">@json($structuredData)</script>
    <style>
        .split-heading[hidden]{display:none!important}
        /* rail-pairs */
        @media (max-width:700px){
            .browse-page .stay-collection .stay-rail{gap:12px!important;scroll-snap-type:x mandatory;scroll-padding:0}
            .browse-page .stay-collection .stay-rail .stay-rail-card{flex:0 0 calc((100% - 12px) / 2)!important;width:calc((100% - 12px) / 2)!important;max-width:none!important;scroll-snap-align:none}
            .browse-page .stay-collection .stay-rail .stay-rail-card:nth-child(odd){scroll-snap-align:start}
            .browse-page .stay-collection .stay-rail .stay-rail-image{border-radius:16px}
            .browse-page .stay-collection .stay-rail .stay-rail-details{font-size:.8rem}
            .browse-page .stay-collection .stay-rail .stay-rail-details strong{font-size:.86rem}
        }
        html,body{max-width:100%;overflow-x:clip}
        .browse-page,.browse-page .stay-collections,.browse-page .stay-collection,.browse-page .collection-heading{min-width:0!important;max-width:100%!important;width:100%;box-sizing:border-box}
        .browse-page .stay-collections{display:block!important}
        .browse-page .stay-rail{width:100%!important;max-width:100%!important;contain:inline-size;overflow-x:auto!important;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch;box-sizing:border-box}
        .browse-page .bs{display:grid;grid-template-columns:1fr 1fr;background:#fff;border:1px solid #dfe6e1;border-radius:22px;box-shadow:0 3px 14px #17221e14;padding:6px;margin:0 0 28px}
        .browse-page .bs>label{display:grid;gap:2px;padding:10px 14px;border-radius:16px;font-size:.72rem;font-weight:700;color:#17221e;min-width:0}
        .browse-page .bs>label:hover{background:#f3f6f4}
        .browse-page .bs>.bs-where{grid-column:1/-1;border-bottom:1px solid #eef1ee;border-radius:16px 16px 0 0}
        .browse-page .bs input{border:0!important;background:transparent!important;box-shadow:none!important;outline:0;padding:0!important;min-height:0!important;height:auto!important;font:inherit;font-size:.95rem;font-weight:400;color:#34433c;width:100%}
        .browse-page .bs-actions{display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:6px 8px}
        .browse-page .bs-filter{border:1px solid #dfe6e1;background:#fff;border-radius:99px;padding:9px 16px;font:inherit;font-size:.88rem;font-weight:650;cursor:pointer}
        .browse-page .bs-go{display:grid;place-items:center;width:46px;height:46px;border:0;border-radius:50%;background:#1f6f54;cursor:pointer;padding:0}
        .browse-page .bs-go svg{width:20px;height:20px;fill:none;stroke:#fff;stroke-width:2.2;stroke-linecap:round}
        .bs-dialog{border:0;border-radius:20px;padding:24px;width:min(92vw,380px);box-shadow:0 20px 60px #0004}
        .bs-dialog::backdrop{background:#0006}
        .bs-dialog h2{margin:0 0 16px;font-size:1.15rem}
        .bs-dialog-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .bs-dialog-fields label{display:grid;gap:6px;font-size:.78rem;font-weight:700}
        .bs-dialog-fields input{border:1px solid #cfd8d2;border-radius:12px;padding:12px;font:inherit;min-height:44px}
        .bs-dialog-actions{display:flex;justify-content:space-between;margin-top:20px}
        .bs-dialog-actions button{border:0;background:transparent;font:inherit;font-weight:650;cursor:pointer;padding:10px 4px;text-decoration:underline}
        .bs-dialog-actions .bs-apply{background:#17221e;color:#fff;border-radius:12px;padding:12px 20px;text-decoration:none}
        @media (min-width:800px){
            .browse-page .bs{grid-template-columns:1.5fr 1fr 1fr .7fr auto;border-radius:99px;padding:8px 8px 8px 12px;align-items:center}
            .browse-page .bs>.bs-where{grid-column:auto;border-bottom:0;border-radius:99px}
            .browse-page .bs>label{border-radius:99px;padding:10px 20px}
        }
        .browse-page .stay-collection{margin:0 0 36px}
        .browse-page .collection-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:14px}
        .browse-page .collection-heading h2{font-size:1.3rem;margin:0}
        .browse-page .stay-rail{display:flex!important;gap:14px!important;overflow-x:auto!important;scroll-snap-type:x mandatory;padding:0 0 10px!important;scrollbar-width:none}
        .browse-page .stay-rail::-webkit-scrollbar{display:none}
        .browse-page .stay-rail-card{flex:0 0 220px!important;width:220px!important;max-width:none!important;scroll-snap-align:start}
        .browse-page .stay-rail-link{display:block!important;color:inherit;text-decoration:none}
        .browse-page .stay-rail-image{display:block!important;width:100%!important;height:auto!important;aspect-ratio:1/1;object-fit:cover;border-radius:20px;background:#e6ebe7}
        .browse-page .stay-rail-details{display:grid!important;gap:2px;margin-top:10px;font-size:.88rem}
        .browse-page .stay-rail-details strong{white-space:normal!important;overflow:visible!important;text-overflow:clip!important;font-size:.95rem;line-height:1.3}
        @media (max-width:700px){
            .browse-page .stay-rail-card{flex:0 0 64vw!important;width:64vw!important;max-width:290px!important}
        }
    </style>
    <meta property="og:image" content="{{ $listings->first()['photos'][0]['url'] ?? '' }}">
    @unless($showStayDiscovery)
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    @endunless
@endpush

@section('content')
    <div class="browse-page">
        <header class="browse-heading">
            <h1>{{ $title }}</h1>
            <p class="lede">{{ $intro }}</p>
        </header>

        

        @if($showStayDiscovery)
            <form class="bs" method="get" action="{{ route('public.stays.index') }}">
                <label class="bs-where"><span>Where</span><input name="city" value="{{ $filters['city'] ?? '' }}" placeholder="Search destinations"></label>
                <label><span>Check-in</span><input type="date" name="check_in" value="{{ $filters['check_in'] ?? '' }}"></label>
                <label><span>Check-out</span><input type="date" name="check_out" value="{{ $filters['check_out'] ?? '' }}"></label>
                <label><span>Guests</span><input type="number" min="1" max="100" name="guests" value="{{ $filters['guests'] ?? 1 }}" aria-label="Number of guests"></label>
                <div class="bs-actions">
                    <button type="button" class="bs-filter" onclick="this.closest('form').querySelector('dialog').showModal()">Filters</button>
                    <button class="bs-go" type="submit" aria-label="Search stays">
                        <svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg>
                    </button>
                </div>
                <dialog class="bs-dialog">
                    <h2>Price per night</h2>
                    <div class="bs-dialog-fields">
                        <label><span>Minimum</span><input id="price_min" type="number" min="0" step="0.01" name="price_min" value="{{ $filters['price_min'] ?? '' }}" placeholder="$"></label>
                        <label><span>Maximum</span><input id="price_max" type="number" min="0" step="0.01" name="price_max" value="{{ $filters['price_max'] ?? '' }}" placeholder="$"></label>
                    </div>
                    <div class="bs-dialog-actions">
                        <button type="button" onclick="this.closest('dialog').close()">Close</button>
                        <button type="submit" class="bs-apply">Show stays</button>
                    </div>
                </dialog>
            </form>
        @else
            <form class="search-panel" method="get" action="{{ route('public.stays.index') }}">
                <div class="search-grid">
                    <div class="field"><label for="city">City</label><input id="city" name="city" value="{{ $filters['city'] ?? '' }}" placeholder="Any city"></div>
                    <div class="field"><label for="check_in">Check-in</label><input id="check_in" type="date" name="check_in" value="{{ $filters['check_in'] ?? '' }}"></div>
                    <div class="field"><label for="check_out">Check-out</label><input id="check_out" type="date" name="check_out" value="{{ $filters['check_out'] ?? '' }}"></div>
                    <div class="field"><label for="guests">Guests</label><input id="guests" type="number" min="1" max="100" name="guests" value="{{ $filters['guests'] ?? 1 }}"></div>
                    <div class="field"><label for="price_min">Min nightly price</label><input id="price_min" type="number" min="0" step="0.01" name="price_min" value="{{ $filters['price_min'] ?? '' }}" placeholder="$"></div>
                    <div class="field"><label for="price_max">Max nightly price</label><input id="price_max" type="number" min="0" step="0.01" name="price_max" value="{{ $filters['price_max'] ?? '' }}" placeholder="$"></div>
                    <button class="button" type="submit">Search stays</button>
                </div>
            </form>
        @endif

        <div class="split-heading" @if($showStayDiscovery) hidden @endif>
            <div>
                <h2>{{ $listings->total() }} {{ Str::plural('stay', $listings->total()) }}</h2>
                @unless($showStayDiscovery)
                    <div class="muted">Locations on the map are approximate.</div>
                @endunless
            </div>
        </div>

        @if($listings->isEmpty())
            <div class="empty">No published stays match those filters yet. Try another city or date range.</div>
        @elseif($showStayDiscovery)
            <div class="stay-collections">
                @foreach($cityGroups as $cityName => $cityListings)
                    <section class="stay-collection" aria-labelledby="collection-{{ Str::slug($cityName) }}">
                        <header class="collection-heading">
                            <h2 id="collection-{{ Str::slug($cityName) }}">{{ $cityName === 'United States' ? 'Explore stays' : 'Places to stay in '.$cityName }}</h2>
                            @if(blank($filters['city'] ?? null) && $cityName !== 'United States')
                                <a href="{{ route('public.stays.city', Str::slug($cityName)) }}" aria-label="See all stays in {{ $cityName }}">See all <span aria-hidden="true">→</span></a>
                            @endif
                        </header>
                        <div class="stay-rail" aria-label="Stays in {{ $cityName }}">
                            @foreach($cityListings as $listing)
                                <article class="stay-rail-card">
                                    <a class="stay-rail-link" href="{{ $listing['url'] }}">
                                        @if(!empty($listing['photos'][0]))
                                            <img class="stay-rail-image" src="{{ $listing['photos'][0]['thumbnail_url'] }}" alt="{{ $listing['photos'][0]['alt'] }}" width="{{ $listing['photos'][0]['width'] }}" height="{{ $listing['photos'][0]['height'] }}" loading="lazy">
                                        @else
                                            <span class="stay-rail-image stay-rail-placeholder" aria-hidden="true">
                                                <svg viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1Z"/></svg>
                                            </span>
                                        @endif
                                        <span class="stay-rail-details">
                                            <strong>{{ $listing['title'] }}</strong>
                                            <span class="muted">{{ $listing['city'] }}{{ $listing['state'] ? ', '.$listing['state'] : '' }}</span>
                                            <span class="muted">{{ $listing['bedrooms'] }} bedrooms · Up to {{ $listing['max_guests'] }} guests</span>
                                            <span class="stay-rail-price">${{ number_format($listing['base_rate_cents'] / 100, 2) }} <span>/ night</span></span>
                                        </span>
                                    </a>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            <div class="pagination">{{ $listings->links() }}</div>
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
    </div>
@endsection

@unless($showStayDiscovery)
    @push('scripts')
        <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            window.addEventListener('load', function () {
                if (!window.L || !document.getElementById('stay-map')) return;
                const stays = @json($mapPoints);
                if (!stays.length) return;
                const map = L.map('stay-map', {scrollWheelZoom: false}).setView([stays[0].latitude, stays[0].longitude], stays.length > 1 ? 5 : 11);
                const esri = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {maxZoom: 19, attribution: 'Tiles &copy; Esri'});
                    const osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'});
                    let fellBack = false;
                    esri.on('tileerror', () => { if (fellBack) return; fellBack = true; map.removeLayer(esri); osm.addTo(map); });
                    esri.addTo(map);
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
@endunless
