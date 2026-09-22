@php
    $siteLogo = \App\Models\Setting::getValue('site_logo');
    $assignmentImage = optional($category->pivot)->header_image;
    $heroImage = $assignmentImage
        ? url('/img/'.$assignmentImage)
        : ($category->header_image
            ? url('/img/'.$category->header_image)
            : (optional($page)->image_1 ? url('/img/'.$page->image_1) : null));
    $displayTitle = optional($category->pivot)->custom_title ?: (optional($page)->title ?: $category->title);
    $displayDescription = optional($category->pivot)->custom_description ?: $category->description;
    $fallbackPhotos = [
        'wifi' => 'https://images.unsplash.com/photo-1606904825846-647eb07f5be2?auto=format&fit=crop&w=1400&q=80',
        'amenities' => 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?auto=format&fit=crop&w=1400&q=80',
        'fitness-center' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=1400&q=80',
        'pool' => 'https://images.unsplash.com/photo-1572331165267-854da2b10ccc?auto=format&fit=crop&w=1400&q=80',
        'restaurants' => 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1400&q=80',
        'bars' => 'https://images.unsplash.com/photo-1514933651103-005eec06c04b?auto=format&fit=crop&w=1400&q=80',
        'parking' => 'https://images.unsplash.com/photo-1506521781263-d8422e82f27a?auto=format&fit=crop&w=1400&q=80',
        'checkout-instructions' => 'https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?auto=format&fit=crop&w=1400&q=80',
    ];
    $heroImage = $heroImage ?: ($fallbackPhotos[$category->slug] ?? null);
    $heroImage = $heroImage ?: $booking->property->heroImageUrl();
    $tone = ['#eef2ff', '#3b65ce'];
@endphp

<x-guest-layout :booking="$booking" :property="$booking->property" :title="$displayTitle" :state="$state">
<section class="guest-detail-shell">
    <div class="guest-status-bar">
        <div>
            @if($siteLogo)
                <img src="{{ url('/img/'.$siteLogo) }}" alt="" class="h-8 max-w-[140px] w-auto object-contain">
            @endif
        </div>
        <span class="guest-status-pill is-checked">Guest Guide</span>
    </div>
    @if($state === 'checkout_available')
        <div class="guest-portal-card p-6">
            <x-checkout-today-card :booking="$booking" :link-only="true" />
        </div>
    @endif
    <x-weather-badge :property="$booking->property" class="guest-weather-card" />
    <header class="guest-detail-hero">
        @if($heroImage)
            <img src="{{ $heroImage }}" alt="{{ $displayTitle }}" loading="eager">
        @endif
    </header>

    <div class="guest-category-scroll-wrap">
    <div class="guest-category-scroll">
        @foreach($categories as $cat)
            @php
                $catTone = ['#eef2ff', '#3b65ce'];
                $catTitle = $cat->pivot->custom_title ?: $cat->title;
                $isActive = $cat->id === $category->id;
            @endphp
            @if($booking->booking_id === 'PREVIEW')
            <a href="{{ route('admin.categories.preview', [$cat, $booking->property]) }}"
               class="guest-category-scroll-item {{ $isActive ? 'is-active' : '' }}"
               style="--scroll-tone: {{ $catTone[0] }}; --scroll-accent: {{ $catTone[1] }};">
            @else
            <a href="{{ route('guest.category', [$booking->booking_id, $booking->token, $cat]) }}"
               class="guest-category-scroll-item {{ $isActive ? 'is-active' : '' }}"
               style="--scroll-tone: {{ $catTone[0] }}; --scroll-accent: {{ $catTone[1] }};">
            @endif
                <span class="guest-category-scroll-icon">
                    @if($cat->guest_icon)
                        <img src="{{ url('/img/'.$cat->guest_icon) }}" alt="{{ $catTitle }}" class="h-full w-full rounded object-cover">
                    @else
                        <x-icon :name="$cat->slug" class="h-4/5 w-4/5" />
                    @endif
                </span>
                <span class="guest-category-scroll-label">{{ $catTitle }}</span>
            </a>
        @endforeach
    </div>
    </div>

    @php
        // No time-window check needed here: GuestController::category() already
        // redirects the guest away before this view ever renders unless $state
        // is guide/checkout_notice/checkout_available, so the guest is always
        // checked in and pre-checkout by the time this page loads.
        $hasArticleContent = ($category->action === 'door_lock' && $locks->isNotEmpty())
            || $category->action === 'local_events'
            || optional($page)->content
            || ($category->slug === 'amenities' && $booking->property->amenities->where('active', true)->count());
    @endphp
    <div class="guest-detail-content-grid">
        @if($hasArticleContent)
        <article class="guest-detail-card">
            @if($category->action === 'door_lock')
                <div class="grid gap-6 {{ $locks->count() > 1 ? 'sm:grid-cols-2' : '' }}">
                    @foreach($locks as $entry)
                        <x-lock-card
                            :booking-id="$booking->booking_id"
                            :token="$booking->token"
                            :lock-id="$entry['lock']->id"
                            :lock-label="$locks->count() > 1 ? $entry['lock']->label : null"
                            :lock-status="$entry['status']"
                            :auto-checkin="! $booking->isMarkedCheckedIn()"
                            :auto-checkout="$booking->isCheckoutDay()"
                        />
                    @endforeach
                </div>
            @elseif($category->action === 'local_events')
                @if($localEvents->isNotEmpty())
                    @php
                        $eventCategories = $localEvents->pluck('category')->unique()->sort()->values();
                    @endphp
                    @if($eventCategories->count() > 1)
                        <div class="guest-event-filters mb-4 flex flex-wrap gap-2">
                            <button type="button" class="guest-event-filter-chip is-active" data-filter="all">All</button>
                            @foreach($eventCategories as $cat)
                                <button type="button" class="guest-event-filter-chip" data-filter="{{ $cat }}">{{ $cat }}</button>
                            @endforeach
                        </div>
                    @endif
                    <div class="guest-event-filters mb-4 flex flex-wrap gap-2" id="guest-date-filters">
                        <button type="button" class="guest-event-filter-chip is-active" data-date-filter="all">All dates</button>
                        <button type="button" class="guest-event-filter-chip" data-date-filter="today">Today</button>
                        <button type="button" class="guest-event-filter-chip" data-date-filter="week">This week</button>
                        <button type="button" class="guest-event-filter-chip" data-date-filter="month">This month</button>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2" id="guest-events-grid"
                         data-booking-id="{{ $booking->booking_id }}"
                         data-token="{{ $booking->token }}"
                         data-category-slug="{{ $category->slug }}"
                         data-page="0">
                        @foreach($localEvents as $event)
                            <a href="{{ $event['url'] }}" target="_blank" rel="noopener" class="guest-event-card" data-category="{{ $event['category'] }}" data-date="{{ $event['date'] }}">
                                @if($event['image'])
                                    <img src="{{ $event['image'] }}" alt="" class="mb-3 h-32 w-full rounded-lg object-cover">
                                @endif
                                <p class="font-semibold text-slate-950">{{ $event['name'] }}</p>
                                @if($event['venue'])
                                    <p class="text-sm text-slate-500">{{ $event['venue'] }}</p>
                                @endif
                                @if($event['date'])
                                    <p class="text-sm text-slate-500">
                                        {{ \Carbon\Carbon::parse($event['date'])->format('M d, Y') }}{{ $event['time'] ? ' at '.\Carbon\Carbon::parse($event['time'])->format('g:i A') : '' }}
                                        @if(count($event['dates'] ?? []) > 1)
                                            <span class="text-slate-400">+ {{ count($event['dates']) - 1 }} more date{{ count($event['dates']) - 1 > 1 ? 's' : '' }}</span>
                                        @endif
                                    </p>
                                @endif
                            </a>
                        @endforeach
                    </div>
                    @if($eventsHasMore)
                        <button type="button" id="guest-events-load-more" class="mt-4 w-full rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Load more events
                        </button>
                    @endif
                @else
                    <p class="text-sm text-slate-500">No local events found right now. Check back soon.</p>
                @endif
            @elseif(optional($page)->content)
                <div class="prose-welcome text-base {{ $category->slug === 'wifi' ? 'wifi-cards' : '' }}">{!! $page->renderContent($booking) !!}</div>
            @endif

            @if($category->slug === 'amenities' && $booking->property->amenities->where('active', true)->count())
                <section class="mt-8 border-t border-slate-200 pt-8">
                    <h2 class="text-xl font-black text-slate-950">Available amenities</h2>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        @foreach($booking->property->amenities->where('active', true) as $amenity)
                            <div class="guest-amenity-card">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-md" style="background: {{ $tone[0] }}; color: {{ $tone[1] }}">
                                    <x-icon :name="\Illuminate\Support\Str::slug($amenity->title)" class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="font-black text-slate-950">{{ $amenity->title }}</p>
                                    @if($amenity->details)
                                        <p class="mt-1 text-sm leading-6 text-slate-600">{!! nl2br(e(strip_tags($amenity->details))) !!}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </article>
        @endif

    </div>
    <a href="{{ route('guest.show', [$booking->booking_id, $booking->token]) }}"
       class="mt-6 flex w-full items-center justify-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800 shadow-sm transition hover:-translate-y-px hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-300">
        <x-icon name="arrow-left" class="h-4 w-4" />
        Dashboard
    </a>
</section>
<script>
(function() {
    var active = document.querySelector('.guest-category-scroll-item.is-active');
    if (active) {
        active.scrollIntoView({ block: 'nearest', inline: 'center' });
    }

    var scroller = document.querySelector('.guest-category-scroll');
    if (scroller) {
        scroller.addEventListener('wheel', function(e) {
            if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
                scroller.scrollLeft += e.deltaY;
                e.preventDefault();
            }
        }, { passive: false });

        var isDown = false;
        var startX, scrollLeftStart;
        scroller.addEventListener('mousedown', function(e) {
            isDown = true;
            scroller.classList.add('is-dragging');
            startX = e.pageX - scroller.offsetLeft;
            scrollLeftStart = scroller.scrollLeft;
        });
        scroller.addEventListener('mouseleave', function() {
            isDown = false;
            scroller.classList.remove('is-dragging');
        });
        scroller.addEventListener('mouseup', function() {
            isDown = false;
            scroller.classList.remove('is-dragging');
        });
        scroller.addEventListener('mousemove', function(e) {
            if (!isDown) return;
            e.preventDefault();
            var x = e.pageX - scroller.offsetLeft;
            var walk = (x - startX) * 1.5;
            scroller.scrollLeft = scrollLeftStart - walk;
        });
    }

    var categoryChips = document.querySelectorAll('.guest-event-filter-chip[data-filter]');
    var dateChips = document.querySelectorAll('.guest-event-filter-chip[data-date-filter]');
    var eventsGrid = document.getElementById('guest-events-grid');

    var activeCategory = 'all';
    var activeDateFilter = 'all';

    function parseLocalDate(dateStr) {
        if (!dateStr) return null;
        var parts = dateStr.split('-');
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    }

    function matchesDateFilter(cardDate, filter) {
        if (filter === 'all') return true;
        var d = parseLocalDate(cardDate);
        if (!d) return false;
        var now = new Date();
        var startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        if (filter === 'today') {
            return d.getTime() === startOfToday.getTime();
        }
        if (filter === 'week') {
            var weekEnd = new Date(startOfToday);
            weekEnd.setDate(weekEnd.getDate() + 7);
            return d >= startOfToday && d < weekEnd;
        }
        if (filter === 'month') {
            var monthEnd = new Date(startOfToday);
            monthEnd.setMonth(monthEnd.getMonth() + 1);
            return d >= startOfToday && d < monthEnd;
        }
        return true;
    }

    function applyFilters() {
        if (!eventsGrid) return;
        var cards = eventsGrid.querySelectorAll('.guest-event-card');
        cards.forEach(function(card) {
            var catMatch = activeCategory === 'all' || card.getAttribute('data-category') === activeCategory;
            var dateMatch = matchesDateFilter(card.getAttribute('data-date'), activeDateFilter);
            card.style.display = (catMatch && dateMatch) ? '' : 'none';
        });
    }

    if (categoryChips.length) {
        categoryChips.forEach(function(chip) {
            chip.addEventListener('click', function() {
                categoryChips.forEach(function(c) { c.classList.remove('is-active'); });
                chip.classList.add('is-active');
                activeCategory = chip.getAttribute('data-filter');
                applyFilters();
            });
        });
    }

    if (dateChips.length) {
        dateChips.forEach(function(chip) {
            chip.addEventListener('click', function() {
                dateChips.forEach(function(c) { c.classList.remove('is-active'); });
                chip.classList.add('is-active');
                activeDateFilter = chip.getAttribute('data-date-filter');
                applyFilters();
            });
        });
    }

    var loadMoreBtn = document.getElementById('guest-events-load-more');
    if (loadMoreBtn && eventsGrid) {
        loadMoreBtn.addEventListener('click', function() {
            var bookingId = eventsGrid.getAttribute('data-booking-id');
            var token = eventsGrid.getAttribute('data-token');
            var categorySlug = eventsGrid.getAttribute('data-category-slug');
            var nextPage = parseInt(eventsGrid.getAttribute('data-page'), 10) + 1;

            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = 'Loading...';

            fetch('/guest/' + bookingId + '/' + token + '/guide/' + categorySlug + '/events?page=' + nextPage)
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    (data.events || []).forEach(function(event) {
                        var card = document.createElement('a');
                        card.href = event.url || '#';
                        card.target = '_blank';
                        card.rel = 'noopener';
                        card.className = 'guest-event-card';
                        card.setAttribute('data-category', event.category || 'Other');
                        card.setAttribute('data-date', event.date || '');

                        var html = '';
                        if (event.image) {
                            html += '<img src="' + event.image + '" alt="" class="mb-3 h-32 w-full rounded-lg object-cover">';
                        }
                        html += '<p class="font-semibold text-slate-950"></p>';
                        if (event.venue) {
                            html += '<p class="text-sm text-slate-500"></p>';
                        }
                        if (event.date) {
                            html += '<p class="text-sm text-slate-500"></p>';
                        }
                        card.innerHTML = html;

                        var paragraphs = card.querySelectorAll('p');
                        var pIndex = 0;
                        paragraphs[pIndex++].textContent = event.name || 'Untitled event';
                        if (event.venue) {
                            paragraphs[pIndex++].textContent = event.venue;
                        }
                        if (event.date) {
                            var d = parseLocalDate(event.date);
                            var dateLabel = d ? d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : event.date;
                            paragraphs[pIndex].textContent = dateLabel + (event.time ? ' at ' + event.time : '');
                        }

                        eventsGrid.appendChild(card);
                    });

                    eventsGrid.setAttribute('data-page', nextPage);
                    applyFilters();

                    if (!data.hasMore) {
                        loadMoreBtn.remove();
                    } else {
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.textContent = 'Load more events';
                    }
                })
                .catch(function() {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = 'Load more events';
                });
        });
    }
})();
</script>
</x-guest-layout>
