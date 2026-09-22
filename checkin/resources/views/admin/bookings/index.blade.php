<x-admin-layout title="Guests">
    <div class="page-header">
        <div>
            <p class="eyebrow">Guest management</p>
            <h1 class="page-title">Guests</h1>
            <p class="page-subtitle">Search guests, review guest progress, copy arrival links, and handle manual approvals.</p>
        </div>
        <a href="{{ route('admin.guests.create') }}" class="btn-primary">Add Guest</a>
    </div>

    <div class="card card-pad mb-5">
        <form id="guest-filter-form" class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[240px]">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input id="guest-live-search" name="search" value="{{ request('search') }}" autocomplete="off" placeholder="Search by guest name, booking ID, or reservation ID" class="input mt-0 pl-9">
                <div id="guest-live-search-results" class="hidden absolute left-0 right-0 top-full z-20 mt-1 max-h-80 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"></div>
            </div>
            <button class="btn-primary gap-2"><x-icon name="search" class="h-4 w-4" />Search</button>
            @if(request('search'))
                <a href="{{ route('admin.guests.index') }}" class="btn-secondary gap-2"><x-icon name="refresh" class="h-4 w-4" />Clear</a>
            @endif
        </form>
        <label class="mt-3 flex w-fit items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="archived" value="1" form="guest-filter-form" @checked($showArchived) onchange="this.form.requestSubmit ? document.getElementById('guest-filter-form').requestSubmit() : document.getElementById('guest-filter-form').submit()"> Show archived</label>
    </div>

    @if($today->isNotEmpty())
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-950">Today</h2>
        <span class="text-sm text-slate-500">{{ $todayTotal }} guest{{ $todayTotal === 1 ? '' : 's' }}</span>
    </div>
    <div id="today-card" class="card mb-4 divide-y divide-slate-100" data-offset="{{ $today->count() }}">
        @foreach($today as $booking)
            @include('admin.bookings.partials.week-guest-row', ['booking' => $booking, 'context' => 'today'])
        @endforeach
    </div>
    @if($today->count() < $todayTotal)
        <div class="mb-8 text-center">
            <button type="button" id="today-show-more" class="btn-secondary">Show More</button>
        </div>
    @endif
    @endif

    @if($thisWeek->isNotEmpty())
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-950">This Week</h2>
        <span class="text-sm text-slate-500">{{ $thisWeekTotal }} guest{{ $thisWeekTotal === 1 ? '' : 's' }}</span>
    </div>
    <div id="this-week-card" class="card mb-4 divide-y divide-slate-100" data-offset="{{ $thisWeek->count() }}">
        @foreach($thisWeek as $booking)
            @include('admin.bookings.partials.week-guest-row', ['booking' => $booking, 'context' => 'this-week'])
        @endforeach
    </div>
    @if($thisWeek->count() < $thisWeekTotal)
        <div class="mb-8 text-center">
            <button type="button" id="this-week-show-more" class="btn-secondary">Show More</button>
        </div>
    @else
        <div class="mb-8"></div>
    @endif
    @endif

    @if($upcomingTotal > 0)
    <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-950">Upcoming</h2>
        <span class="text-sm text-slate-500">{{ $upcomingTotal }} guest{{ $upcomingTotal === 1 ? '' : 's' }}</span>
    </div>
    <div id="upcoming-card" class="card mb-4 divide-y divide-slate-100" data-offset="{{ $upcoming->count() }}" data-has-more="{{ $upcoming->count() < $upcomingTotal ? '1' : '0' }}">
        @foreach($upcoming as $booking)
            @include('admin.bookings.partials.week-guest-row', ['booking' => $booking, 'context' => 'upcoming'])
        @endforeach
    </div>
    @if($upcoming->count() < $upcomingTotal)
        <div class="mb-8 text-center">
            <button type="button" id="upcoming-show-more" class="btn-secondary">Show More</button>
        </div>
    @endif
    @endif

    @if($showArchived)
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-950">Archived Guests</h2>
        </div>
        <div class="table-wrap">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Guest</th><th>Property</th><th>Stay</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @forelse($bookings as $booking)
                            @include('admin.bookings.partials.guest-row', ['booking' => $booking])
                        @empty
                            <tr><td colspan="5" class="py-12 text-center text-slate-500">No archived guests found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-5">{{ $bookings->links() }}</div>
    @endif

    @if($today->isEmpty() && $thisWeek->isEmpty() && $upcomingTotal === 0)
        <div class="card card-pad text-center text-slate-500">No guests checking in or out this week or coming up.</div>
    @endif

    <script>
    (function () {
        // Live search dropdown (task 7): show matches as the admin types
        // instead of requiring a form submit + full table filter.
        const input   = document.getElementById('guest-live-search');
        const results = document.getElementById('guest-live-search-results');
        const searchUrl = @json(route('admin.guests.search'));
        let debounceTimer = null;
        let activeController = null;

        const todayCard = document.getElementById('today-card');
        const todayShowMoreBtn = document.getElementById('today-show-more');
        const todayMoreUrl = @json(route('admin.guests.today'));

        todayShowMoreBtn?.addEventListener('click', function () {
            const offset = parseInt(todayCard.dataset.offset || '0', 10);
            todayShowMoreBtn.disabled = true;
            todayShowMoreBtn.textContent = 'Loading...';
            fetch(todayMoreUrl + '?offset=' + offset + '&limit=5', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(function (data) {
                    todayCard.insertAdjacentHTML('beforeend', data.html);
                    todayCard.dataset.offset = data.next_offset;
                    if (!data.has_more) todayShowMoreBtn.remove();
                    else {
                        todayShowMoreBtn.disabled = false;
                        todayShowMoreBtn.textContent = 'Show More';
                    }
                })
                .catch(function () {
                    todayShowMoreBtn.disabled = false;
                    todayShowMoreBtn.textContent = 'Show More';
                });
        });

        function hideResults() {
            results.classList.add('hidden');
            results.innerHTML = '';
        }

        function renderResults(items) {
            if (!items.length) {
                results.innerHTML = '<div class="px-3 py-3 text-sm text-slate-500">No matching guests</div>';
                results.classList.remove('hidden');
                return;
            }

            results.innerHTML = items.map(item => `
                <a href="${item.url}" class="flex items-center justify-between gap-3 px-3 py-2 text-sm hover:bg-slate-50">
                    <span class="min-w-0">
                        <span class="block truncate font-semibold text-slate-950">${item.name}</span>
                        <span class="block truncate text-slate-500">${item.property ?? ''} &middot; ${item.stay}</span>
                    </span>
                    <span class="badge badge-${item.status ? item.status.toLowerCase().replace(/\\s+/g, '_') : 'inactive'} shrink-0">${item.status}</span>
                </a>
            `).join('');
            results.classList.remove('hidden');
        }

        input?.addEventListener('input', function () {
            const q = this.value.trim();
            clearTimeout(debounceTimer);

            if (!q) {
                hideResults();
                return;
            }

            debounceTimer = setTimeout(function () {
                activeController?.abort();
                activeController = new AbortController();

                fetch(searchUrl + '?q=' + encodeURIComponent(q), { signal: activeController.signal, headers: { 'Accept': 'application/json' } })
                    .then(r => r.json())
                    .then(renderResults)
                    .catch(err => { if (err.name !== 'AbortError') hideResults(); });
            }, 200);
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#guest-live-search') && !e.target.closest('#guest-live-search-results')) {
                hideResults();
            }
        });

        // "Show More" batch loading for the Upcoming card: loads 5 more
        // rows at a time with no full page reload.
        const upcomingCard = document.getElementById('upcoming-card');
        const upcomingShowMoreBtn = document.getElementById('upcoming-show-more');
        const upcomingMoreUrl = @json(route('admin.guests.upcoming'));

        upcomingShowMoreBtn?.addEventListener('click', function () {
            const offset = parseInt(upcomingCard.dataset.offset || '0', 10);

            upcomingShowMoreBtn.disabled = true;
            upcomingShowMoreBtn.textContent = 'Loading...';

            fetch(upcomingMoreUrl + '?offset=' + offset + '&limit=5', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(function (data) {
                    upcomingCard.insertAdjacentHTML('beforeend', data.html);
                    upcomingCard.dataset.offset = data.next_offset;

                    if (!data.has_more) {
                        upcomingShowMoreBtn.remove();
                    } else {
                        upcomingShowMoreBtn.disabled = false;
                        upcomingShowMoreBtn.textContent = 'Show More';
                    }
                })
                .catch(function () {
                    upcomingShowMoreBtn.disabled = false;
                    upcomingShowMoreBtn.textContent = 'Show More';
                });
        });

        const thisWeekCard = document.getElementById('this-week-card');
        const thisWeekShowMoreBtn = document.getElementById('this-week-show-more');
        const thisWeekMoreUrl = @json(route('admin.guests.this-week'));

        thisWeekShowMoreBtn?.addEventListener('click', function () {
            const offset = parseInt(thisWeekCard.dataset.offset || '0', 10);

            thisWeekShowMoreBtn.disabled = true;
            thisWeekShowMoreBtn.textContent = 'Loading...';

            fetch(thisWeekMoreUrl + '?offset=' + offset + '&limit=5', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(function (data) {
                    thisWeekCard.insertAdjacentHTML('beforeend', data.html);
                    thisWeekCard.dataset.offset = data.next_offset;

                    if (!data.has_more) {
                        thisWeekShowMoreBtn.remove();
                    } else {
                        thisWeekShowMoreBtn.disabled = false;
                        thisWeekShowMoreBtn.textContent = 'Show More';
                    }
                })
                .catch(function () {
                    thisWeekShowMoreBtn.disabled = false;
                    thisWeekShowMoreBtn.textContent = 'Show More';
                });
        });
    })();
    </script>
</x-admin-layout>
