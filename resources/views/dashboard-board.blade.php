@php
    $tabs = $dayBoard['tabs'];
    $hosting = $dayBoard['hosting'];
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

    {{-- Day tabs --}}
    <div class="-mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1 md:mx-0 md:grid md:grid-cols-5 md:overflow-visible md:px-0">
        @foreach($tabs as $key => $t)
            <button type="button" @click="setTab('{{ $key }}')"
                :class="tab === '{{ $key }}' ? 'bg-[var(--theme-primary)] text-white shadow' : 'border border-slate-200 bg-white text-slate-800 hover:bg-slate-50'"
                class="flex min-w-[8rem] shrink-0 flex-col items-center justify-center rounded-xl px-4 py-2.5 text-center transition md:min-w-0">
                <span class="flex items-center gap-2 text-base font-bold">
                    {{ $t['label'] }}
                    @if($t['attention'] > 0)
                        <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">{{ $t['attention'] }}</span>
                    @endif
                </span>
                <span class="text-sm" :class="tab === '{{ $key }}' ? 'text-white/80' : 'text-slate-500'">{{ $t['sub'] }}</span>
            </button>
        @endforeach
    </div>

    @foreach($tabs as $key => $t)
        <div x-show="tab === '{{ $key }}'" x-cloak class="flex flex-col gap-5">
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" @if($key === 'today') data-tour="guests-today" @endif>
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-4 md:px-5">
                    <h2 class="text-xl font-bold text-slate-950">{{ $t['label'] }} <span class="ml-1 text-base font-medium text-slate-500">{{ $t['range'] }}</span></h2>
                    <div class="flex flex-wrap items-center gap-2 text-sm font-semibold">
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-emerald-800">{{ $t['arrivals'] }} check-in{{ $t['arrivals'] === 1 ? '' : 's' }}</span>
                        <span class="rounded-full bg-sky-100 px-3 py-1 text-sky-800">{{ $t['checkouts'] }} check-out{{ $t['checkouts'] === 1 ? '' : 's' }}</span>
                        @if($t['attention'] > 0)
                            <span class="rounded-full bg-slate-900 px-3 py-1 text-white">{{ $t['attention'] }} need{{ $t['attention'] === 1 ? 's' : '' }} action</span>
                        @endif
                    </div>
                </div>

                @if(count($t['items']))
                    <div class="hidden md:block"><div class="{{ $grid }} border-b border-slate-200 bg-slate-50 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                        <span>Time</span><span>Guest</span><span class="hidden md:block">Type</span><span class="hidden md:block">Property</span><span class="hidden md:block">Action</span><span></span>
                    </div></div>
                @endif

                <div class="divide-y divide-slate-100">
                    @php $lastDate = null; @endphp
                    @forelse($t['items'] as $item)
                        @if($key === 'week' && $item['date'] !== $lastDate)
                            @php $lastDate = $item['date']; @endphp
                            <p class="bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700 md:px-5">{{ $item['date_long'] }}</p>
                        @endif
                        @php
                            $hasAction = $item['todo'] || $item['ok'] || count($item['notes']);
                            $openGuest = ['url' => route('admin.guests.show', $item['booking']).'?embed=1', 'title' => $item['guest']];
                        @endphp
                        <div>
                            {{-- Desktop: table row --}}
                            <div class="hidden md:block">
                                <div class="{{ $grid }} py-4 md:items-center">
                                    <p class="text-[15px] font-semibold text-slate-900">{{ $item['time'] ?: 'Anytime' }}</p>
                                    <button type="button" class="break-words text-left text-base font-semibold text-slate-950 hover:underline" @click="$dispatch('open-guest', @js($openGuest))">{{ $item['guest'] }}</button>
                                    <div><span class="inline-block rounded-md px-2 py-0.5 text-xs font-bold uppercase tracking-wide {{ $chips[$item['kind']]['class'] }}">{{ $chips[$item['kind']]['label'] }}</span></div>
                                    <p class="break-words text-[15px] text-slate-600">{{ $item['property'] }}</p>
                                    <div class="space-y-1">
                                        @if($item['todo'])
                                            <p class="text-[15px] font-semibold {{ $text[$item['todo']['tone']] ?? 'text-slate-800' }}">{{ $item['todo']['text'] }}</p>
                                        @endif
                                        @if($item['ok'])
                                            <p class="text-[15px] text-slate-600">{{ $item['ok'] }}</p>
                                        @endif
                                        @foreach($item['notes'] as $note)
                                            <p class="text-[15px] leading-snug">
                                                <span class="font-semibold {{ $text[$note['tone']] ?? 'text-slate-700' }}">{{ $note['text'] }}</span>
                                                @if(! empty($note['detail']))<span class="block text-slate-600">{{ $note['detail'] }}</span>@endif
                                            </p>
                                        @endforeach
                                    </div>
                                    <div class="flex items-center justify-end gap-2">
                                        @if($item['assign'])
                                            <button type="button" class="btn-primary justify-center whitespace-nowrap text-sm" @click="openQuickAssign(@js($item['assign']))">Assign cleaner</button>
                                        @endif
                                        <button type="button" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-1.5 text-sm font-semibold text-slate-800 hover:bg-slate-50" @click="$dispatch('open-guest', @js($openGuest))">View</button>
                                    </div>
                                </div>
                            </div>

                            {{-- Mobile: card --}}
                            <div class="px-4 py-4 md:hidden">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[15px] font-bold text-slate-900">{{ $item['time'] ?: 'Anytime' }}</p>
                                    <span class="inline-block rounded-md px-2 py-0.5 text-xs font-bold uppercase tracking-wide {{ $chips[$item['kind']]['class'] }}">{{ $chips[$item['kind']]['label'] }}</span>
                                </div>
                                <button type="button" class="mt-2 block w-full break-words text-left text-[17px] font-semibold text-slate-950" @click="$dispatch('open-guest', @js($openGuest))">{{ $item['guest'] }}</button>
                                <p class="mt-0.5 break-words text-[15px] text-slate-600">{{ $item['property'] }}</p>

                                @if($hasAction)
                                    <div class="mt-3 space-y-1.5">
                                        @if($item['todo'])
                                            <p class="text-[15px] font-semibold {{ $text[$item['todo']['tone']] ?? 'text-slate-800' }}">{{ $item['todo']['text'] }}</p>
                                        @endif
                                        @if($item['ok'])
                                            <p class="text-[15px] text-slate-600">{{ $item['ok'] }}</p>
                                        @endif
                                        @foreach($item['notes'] as $note)
                                            <p class="text-[15px] leading-snug">
                                                <span class="font-semibold {{ $text[$note['tone']] ?? 'text-slate-700' }}">{{ $note['text'] }}</span>
                                                @if(! empty($note['detail']))<span class="block text-slate-600">{{ $note['detail'] }}</span>@endif
                                            </p>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-3 flex gap-2 border-t border-slate-100 pt-3">
                                    @if($item['assign'])
                                        <button type="button" class="btn-primary flex-1 justify-center text-sm" @click="openQuickAssign(@js($item['assign']))">Assign cleaner</button>
                                    @endif
                                    <button type="button" class="inline-flex flex-1 items-center justify-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-800 hover:bg-slate-50" @click="$dispatch('open-guest', @js($openGuest))">View</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-10 text-center text-base text-slate-500">Nothing scheduled{{ $key === 'week' ? '' : ' for '.strtolower($t['label']) }}.</p>
                    @endforelse
                </div>
            </section>

            @if($key === 'today')
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <h3 class="flex items-center justify-between border-b border-slate-200 px-4 py-4 text-base font-bold text-slate-950 md:px-5">
                        Currently hosting
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-sm font-semibold text-slate-600">{{ count($hosting) }}</span>
                    </h3>
                    @if(count($hosting))
                        <div class="hidden md:block"><div class="{{ $hgrid }} border-b border-slate-200 bg-slate-50 py-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                            <span>Guest</span><span class="hidden md:block">Property</span><span>Checks out</span>
                        </div></div>
                        <div class="divide-y divide-slate-100">
                            @foreach($hosting as $h)
                                @php $hostOpen = ['url' => route('admin.guests.show', $h['booking']).'?embed=1', 'title' => $h['guest']]; @endphp
                                <div>
                                    <div class="hidden md:block">
                                        <div class="{{ $hgrid }} py-3 md:items-center">
                                            <button type="button" class="break-words text-left text-base font-semibold text-slate-950 hover:underline" @click="$dispatch('open-guest', @js($hostOpen))">{{ $h['guest'] }}</button>
                                            <p class="break-words text-[15px] text-slate-600">{{ $h['property'] }}</p>
                                            <p class="text-[15px] font-semibold text-slate-800">Checks out {{ $h['when'] }}@if($h['time']) · {{ $h['time'] }}@endif</p>
                                        </div>
                                    </div>
                                    <div class="px-4 py-4 md:hidden">
                                        <button type="button" class="block w-full break-words text-left text-[17px] font-semibold text-slate-950" @click="$dispatch('open-guest', @js($hostOpen))">{{ $h['guest'] }}</button>
                                        <p class="mt-0.5 break-words text-[15px] text-slate-600">{{ $h['property'] }}</p>
                                        <p class="mt-2 text-[15px] font-semibold text-slate-800">Checks out {{ $h['when'] }}@if($h['time']) · {{ $h['time'] }}@endif</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="px-5 py-6 text-center text-[15px] text-slate-500">No guests staying right now.</p>
                    @endif
                </section>
            @endif
        </div>
    @endforeach

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
