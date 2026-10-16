{{-- Unified Today / Tomorrow / Upcoming cards: checkouts + cleaning coverage + arrivals in ONE card per period. --}}
@php
    $groups = $checkoutBoard['groups'];
    $summary = $checkoutBoard['summary'];
    $dates = $checkoutBoard['dates'];
    $todayStr = $dates['today']->toDateString();
    $tomorrowStr = $dates['tomorrow']->toDateString();

    $priorityGuests = collect($priorityGuests ?? []);
    $priorityIds = $priorityGuests->pluck('id');
    $arrivalsToday = $priorityGuests->concat(collect($todayGuests ?? [])->filter(fn ($b) => $b->check_in_date?->toDateString() === $todayStr && ! $priorityIds->contains($b->id)))->values();
    $arrivalsTomorrow = collect($upcomingGuests ?? [])->filter(fn ($b) => $b->check_in_date?->toDateString() === $tomorrowStr && ! $priorityIds->contains($b->id))->values();
    $arrivalsLater = collect($upcomingGuests ?? [])->filter(fn ($b) => $b->check_in_date?->toDateString() > $tomorrowStr && ! $priorityIds->contains($b->id))->values();
    $laterShown = $arrivalsLater->take(10);

    $cards = [
        'today' => ['title' => 'Today', 'sub' => $dates['today']->format('l, M j'), 'checkouts' => $groups['today'], 'arrivals' => $arrivalsToday],
        'tomorrow' => ['title' => 'Tomorrow', 'sub' => $dates['tomorrow']->format('l, M j'), 'checkouts' => $groups['tomorrow'], 'arrivals' => $arrivalsTomorrow],
        'upcoming' => ['title' => 'Upcoming', 'sub' => 'Checkouts to '.$dates['later_end']->format('M j').', arrivals after tomorrow', 'checkouts' => $groups['later'], 'arrivals' => $laterShown],
    ];
@endphp

<div id="checkout-board" class="flex flex-col gap-3" x-data="{
    quickAssignOpen: false,
    quickAssignSaving: false,
    quickAssignError: '',
    quickAssignConflicts: [],
    quickAssignConflictKey: '',
    quickAssignKey() { return JSON.stringify([this.form.property_id, this.form.date, this.form.housekeeper_id, this.form.scheduled_time || '']); },
    get quickAssignConfirmable() { return this.quickAssignConflicts.length > 0 && this.quickAssignConflictKey === this.quickAssignKey(); },
    form: { property_id: '', property_name: '', date: '', session_id: null, housekeeper_id: '', scheduled_time: '', cleaners: [] },
    openQuickAssign(data) { window.dispatchEvent(new CustomEvent('open-schedule', { detail: { property_id: data.property_id, date: data.date, session_id: data.session_id, booking_id: data.booking_id, housekeeper_id: data.housekeeper_id, time: data.scheduled_time } })); },
    closeQuickAssign() {
        if (!this.quickAssignSaving) this.quickAssignOpen = false;
    },
    async submitQuickAssign(confirm = false) {
        this.quickAssignSaving = true;
        this.quickAssignError = '';
        this.quickAssignConflicts = [];
        try {
            const response = await fetch('{{ route('admin.cleaning-jobs.quick-assign') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                },
                body: JSON.stringify({
                    property_id: this.form.property_id,
                    scheduled_date: this.form.date,
                    session_id: this.form.session_id,
                    housekeeper_id: this.form.housekeeper_id,
                    scheduled_time: this.form.scheduled_time || null,
                    confirm_conflicts: confirm
                })
            });
            const result = await response.json();
            if (response.status === 409 && result.needs_confirmation) {
                this.quickAssignConflicts = result.conflicts || [];
                this.quickAssignConflictKey = this.quickAssignKey();
                return;
            }
            if (!response.ok) {
                const firstError = result.errors ? Object.values(result.errors).flat()[0] : null;
                throw new Error(firstError || result.message || 'The cleaner could not be assigned.');
            }
            const row = document.getElementById(`checkout-row-${this.form.property_id}-${this.form.date.replace(/-/g, '')}`);
            const coverage = document.getElementById('checkout-coverage');
            if (!row || !coverage) throw new Error('The checkout board changed. Refresh the dashboard and try again.');
            row.outerHTML = result.row_html;
            coverage.innerHTML = result.coverage_html;
            this.quickAssignOpen = false;
        } catch (error) {
            this.quickAssignError = error.message || 'The cleaner could not be assigned.';
        } finally {
            this.quickAssignSaving = false;
        }
    }
}">
    <div id="checkout-coverage">
        @include('dashboard-checkout-coverage', ['summary' => $summary])
    </div>

    @foreach($cards as $key => $card)
        @php
            $nCheckouts = $card['checkouts']->count();
            $nArrivals = $key === 'upcoming' ? $arrivalsLater->count() : $card['arrivals']->count();
            $anchor = $key === 'today' ? 'guests-today' : 'dashboard-'.$key;
        @endphp

        @if($nCheckouts === 0 && $nArrivals === 0)
            {{-- Empty periods collapse to one slim line --}}
            <div class="card flex items-center justify-between px-4 py-2.5 scroll-mt-24" id="{{ $anchor }}" @if($key === 'today') data-tour="guests-today" @endif>
                <p class="text-sm"><span class="font-bold text-slate-950">{{ $card['title'] }}</span><span class="ml-2 text-slate-500">{{ $card['sub'] }}</span></p>
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Nothing scheduled</span>
            </div>
        @else
            <section class="card overflow-hidden scroll-mt-24" id="{{ $anchor }}" @if($key === 'today') data-tour="guests-today" @endif>
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
                    <p class="text-sm"><span class="font-bold text-slate-950">{{ $card['title'] }}</span><span class="ml-2 text-slate-500">{{ $card['sub'] }}</span></p>
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ $nCheckouts }} checkout{{ $nCheckouts === 1 ? '' : 's' }} &middot; {{ $nArrivals }} arrival{{ $nArrivals === 1 ? '' : 's' }}
                    </span>
                </div>

                @if($nCheckouts > 0)
                    <p class="bg-slate-50 px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-500">Checkouts and cleaning</p>
                    <div class="divide-y divide-slate-100">
                        @foreach($card['checkouts'] as $row)
                            @include('dashboard-checkout-row', ['row' => $row])
                        @endforeach
                    </div>
                @endif

                @if($nArrivals > 0)
                    <p class="bg-slate-50 px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-500">Arrivals</p>
                    <div class="divide-y divide-slate-100">
                        @foreach($card['arrivals'] as $booking)
                            @include('dashboard-arrival-row', ['booking' => $booking, 'today' => $today, 'context' => $key, 'pinned' => $key === 'today' && $priorityIds->contains($booking->id)])
                        @endforeach
                        @if($key === 'upcoming' && $arrivalsLater->count() > $laterShown->count())
                            <a href="{{ route('admin.guests.index') }}" class="block px-4 py-3 text-sm font-semibold text-[var(--theme-primary)] hover:bg-slate-50">
                                View all {{ $arrivalsLater->count() }} upcoming arrivals
                            </a>
                        @endif
                    </div>
                @endif
            </section>
        @endif
    @endforeach

    <x-schedule-drawer />
</div>
