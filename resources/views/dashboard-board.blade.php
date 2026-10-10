@php
    $cards = $dayBoard['cards'];
    $chips = [
        'arrival' => ['label' => 'Check-in', 'class' => 'bg-emerald-100 text-emerald-800'],
        'checkout' => ['label' => 'Check-out', 'class' => 'bg-sky-100 text-sky-800'],
    ];
    $text = ['red' => 'text-red-700', 'amber' => 'text-amber-800'];
    // Same table on phone (3 columns) and desktop (5 columns)
    $grid = 'grid grid-cols-[4.75rem_minmax(0,1fr)_auto] gap-x-3 gap-y-1 px-4 md:grid-cols-[6.5rem_minmax(0,1.2fr)_6.5rem_minmax(0,1fr)_minmax(0,1.6fr)_auto] md:gap-x-4 md:px-5';
    $hgrid = 'grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-x-3 gap-y-1 px-4 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1.5fr)] md:gap-x-4 md:px-5';
@endphp

<div id="checkout-board" class="flex flex-col" x-data="{
    tab: 'today',
    setTab(t) { this.tab = t; history.replaceState(null, '', '#' + t); },
    init() {
        const h = location.hash.slice(1);
        if (['today', 'tomorrow', 'day2', 'day3', 'week'].includes(h)) this.tab = h;
    },
    quickAssignOpen: false,
    quickAssignSaving: false,
    quickAssignError: '',
    quickAssignConflicts: [],
    quickAssignConflictKey: '',
    quickAssignKey() { return JSON.stringify([this.form.property_id, this.form.date, this.form.housekeeper_id, this.form.scheduled_time || '']); },
    get quickAssignConfirmable() { return this.quickAssignConflicts.length > 0 && this.quickAssignConflictKey === this.quickAssignKey(); },
    form: { property_id: '', property_name: '', date: '', session_id: null, housekeeper_id: '', scheduled_time: '', cleaners: [] },
    openQuickAssign(data) {
        this.form = { ...data, housekeeper_id: data.housekeeper_id ?? '' };
        this.quickAssignError = '';
        this.quickAssignConflicts = [];
        this.quickAssignOpen = true;
    },
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
            this.quickAssignOpen = false;
            window.location.reload();
        } catch (error) {
            this.quickAssignError = error.message || 'The cleaner could not be assigned.';
        } finally {
            this.quickAssignSaving = false;
        }
    }
}">

    {{-- Stacked day cards: Today, Tomorrow, In 2 days, Upcoming --}}
    <div class="flex flex-col gap-5">
        @foreach($cards as $key => $c)
            <section class="rounded-xl border border-slate-200 bg-white pb-2 shadow-sm" @if($key === 'today') data-tour="guests-today" @endif>
                <h2 class="px-4 pb-1 pt-4 font-bold text-slate-950 md:px-5" style="font-size:20px;line-height:1.2">{{ $c['label'] }} <span class="ml-1 text-sm font-medium text-slate-500">{{ $c['range'] }}</span></h2>

                @php $lastDate = null; $any = false; @endphp
                @foreach($c['sections'] as $sec)
                    @foreach($sec['rows'] as $row)
                        @php $any = true; @endphp
                        @if($c['grouped'] && $row['date'] !== $lastDate)
                            @php $lastDate = $row['date']; @endphp
                            <p class="px-4 pt-3 text-xs font-bold uppercase tracking-wide text-slate-500 md:px-5">{{ $row['date_long'] }}</p>
                        @endif
                        @include('dashboard-guest-row', ['row' => $row])
                    @endforeach
                @endforeach

                @unless($any)
                    <p class="px-5 py-8 text-center text-base text-slate-500">Nothing scheduled{{ $key === 'week' ? '' : ' for '.strtolower($c['label']) }}.</p>
                @endunless
            </section>
        @endforeach
    </div>

    <div x-data="dashboardGuest()" @open-guest.window="openGuest($event.detail)">
        <x-drawer title="Guest" open="guestOpen" on-close="closeGuest()" id="dashboard-guest-drawer">
            <template x-if="guestOpen">
                <iframe :src="url" :title="title" class="w-full rounded border border-slate-200" style="height: calc(100vh - 8rem);"></iframe>
            </template>
        </x-drawer>
    </div>
    <script>
        window.dashboardGuest = () => ({
            guestOpen: false,
            url: '',
            title: 'Guest',
            openGuest(d) {
                this.url = d.url;
                this.title = d.title || 'Guest';
                this.guestOpen = true;
            },
            closeGuest() {
                this.guestOpen = false;
                this.url = '';
                window.location.reload();
            }
        });
    </script>

    <x-drawer title="Assign cleaner" open="quickAssignOpen" on-close="closeQuickAssign()" id="quick-assign-drawer">
        <form class="space-y-5" @submit.prevent="submitQuickAssign()">
            <div class="rounded-lg bg-slate-50 p-3 text-sm">
                <p class="font-semibold text-slate-900" x-text="form.property_name"></p>
                <p class="text-slate-600" x-text="form.date"></p>
                <p class="text-slate-600">Choose an eligible cleaner assigned to this property.</p>
            </div>
            <div>
                <label for="quick-assign-cleaner" class="field-label">Cleaner</label>
                <select id="quick-assign-cleaner" class="input mt-1 w-full" x-model.number="form.housekeeper_id" required>
                    <option value="">Select a cleaner</option>
                    <template x-for="cleaner in form.cleaners" :key="cleaner.id">
                        <option :value="cleaner.id" x-text="cleaner.name"></option>
                    </template>
                </select>
                <p x-show="form.cleaners.length === 0" class="mt-2 text-sm text-amber-700">
                    No active cleaners are assigned to this property. Assign a cleaner to the property first.
                </p>
            </div>
            <div>
                <label for="quick-assign-time" class="field-label">Scheduled time</label>
                <input id="quick-assign-time" type="time" class="input mt-1 w-full" x-model="form.scheduled_time">
            </div>
            <div x-show="quickAssignConfirmable" x-cloak role="alert" class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                <p class="font-semibold">This assignment has conflicts:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <template x-for="c in quickAssignConflicts" :key="c.message"><li x-text="c.message"></li></template>
                </ul>
                <p class="mt-2 text-amber-800">Review them, then choose Assign anyway or change the cleaner, date or time.</p>
            </div>
            <p x-show="quickAssignError" x-text="quickAssignError" role="alert" class="text-sm font-medium text-red-700"></p>
            <div class="flex justify-end gap-3">
                <button type="button" class="btn-secondary" @click="closeQuickAssign()" :disabled="quickAssignSaving">Cancel</button>
                <button type="button" class="btn-primary" x-show="quickAssignConfirmable" @click="submitQuickAssign(true)" :disabled="quickAssignSaving">
                    <span x-text="quickAssignSaving ? 'Saving…' : 'Assign anyway'"></span>
                </button>
                <button type="submit" class="btn-primary" x-show="!quickAssignConfirmable" :disabled="quickAssignSaving || !form.housekeeper_id || form.cleaners.length === 0">
                    <span x-text="quickAssignSaving ? 'Saving…' : 'Save assignment'"></span>
                </button>
            </div>
        </form>
    </x-drawer>
</div>
