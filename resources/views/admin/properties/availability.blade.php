<x-admin-layout :title="$property->name.' - Availability'">
    <div class="page-header">
        <div>
            <p class="eyebrow">Property setup</p>
            <h1 class="page-title">Availability</h1>
            <p class="page-subtitle">{{ $property->name }}</p>
        </div>
        <a href="{{ route('admin.properties.edit', $property) }}" class="btn-secondary">Back to property</a>
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @php
        $seeded = (bool) $property->channex_availability_seeded_at;
        $days = $availabilities->mapWithKeys(fn ($r) => [
            $r->date->format('Y-m-d') => [
                's' => $r->status === 'booked' ? 'booked' : ($r->is_available ? 'open' : 'blocked'),
                'r' => $r->rate !== null ? (float) $r->rate : null,
            ],
        ]);
        $bookedCount = $availabilities->where('status', 'booked')->count();
    @endphp

    <div class="grid gap-6">

        {{-- Sync status --}}
        <section class="card card-pad">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-slate-700">
                <span><strong>{{ $pendingCount }}</strong> change(s) waiting to send to Channex</span>
                <span class="{{ $failedCount ? 'font-semibold text-red-700' : '' }}"><strong>{{ $failedCount }}</strong> failed</span>
                <span>Last PriceLabs pull:
                    <strong>{{ $property->pricelabs_synced_at ? \Carbon\Carbon::parse($property->pricelabs_synced_at)->diffForHumans() : 'never' }}</strong></span>
            </div>
            @if($failedCount)
                <p class="field-help mt-2 text-red-700">Some changes failed after 10 tries and are no longer retried. @if($lastOutboxError) Last error: {{ $lastOutboxError }}@endif</p>
                <form method="post" action="{{ route('admin.properties.availability.retry-failed', $property) }}" class="mt-2">
                    @csrf
                    <button type="submit" class="btn-secondary">Retry failed</button>
                </form>
            @endif
        </section>

        {{-- Calendar --}}
        <section class="card card-pad" x-data="availCal(@js($days), '{{ now()->toDateString() }}')">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-secondary" @click="prev()" :disabled="!canPrev()">&larr;</button>
                    <h2 class="section-title min-w-[10rem] text-center" x-text="title()"></h2>
                    <button type="button" class="btn-secondary" @click="next()">&rarr;</button>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-600">
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-emerald-100 ring-1 ring-emerald-300"></span>Available</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-violet-300 ring-1 ring-violet-500"></span>Booked</span>
                    <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-red-100 ring-1 ring-red-300"></span>Blocked</span>
                </div>
            </div>

            <p class="field-help mt-2">{{ $blockedCount }} blocked &middot; {{ $bookedCount }} booked in the synced period. Rates come from PriceLabs.</p>

            <div class="mt-4 grid grid-cols-7 gap-1 text-center text-xs font-bold uppercase tracking-wide text-slate-500">
                <template x-for="d in ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']" :key="d"><div x-text="d"></div></template>
            </div>

            <div class="mt-1 grid grid-cols-7 gap-1">
                <template x-for="c in cells()" :key="c.key">
                    <div>
                        <template x-if="c.blank"><div class="h-16"></div></template>
                        <template x-if="!c.blank">
                            <button type="button"
                                @click="pick(c.date)"
                                :disabled="c.past"
                                :class="[cls(c), picked(c.date) ? 'ring-2 ring-slate-900' : '', c.past ? 'opacity-40 cursor-not-allowed' : 'hover:brightness-95']"
                                class="flex h-16 w-full flex-col items-center justify-center rounded-lg text-sm transition">
                                <span class="font-semibold" x-text="c.day"></span>
                                <span class="text-[11px] text-slate-600" x-text="c.rate ? '$' + Math.round(c.rate) : ''"></span>
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            <form method="post" action="{{ route('admin.properties.availability.update-range', $property) }}" class="mt-5 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                @csrf
                <input type="hidden" name="date_from" :value="start">
                <input type="hidden" name="date_to" :value="end || start">
                <input type="hidden" name="availability" value="">
                <div class="flex-1 min-w-[220px] text-sm text-slate-700">
                    <template x-if="!start"><span>Click a date, then a second date to select a range.</span></template>
                    <template x-if="start"><span><strong x-text="label()"></strong></span></template>
                </div>
                <button type="button" class="btn-primary" :disabled="!start" @click="go('block', $event)">Block</button>
                <button type="button" class="btn-secondary" :disabled="!start" @click="go('open', $event)">Open</button>
                <button type="button" class="btn-secondary" @click="clear()" x-show="start">Clear</button>
            </form>
            <p class="field-help mt-2">Changes reach Channex within about a minute. Booked nights stay closed.</p>
        </section>

        {{-- Rates and restrictions --}}
        <section class="card card-pad">
            <h2 class="section-title">Rates and restrictions</h2>
            <p class="section-copy">Set a rate or stay rules for a date range. Only the fields you fill in change. Changes reach Channex within about a minute. PriceLabs can overwrite dates it returns on its next pull.</p>
            <form method="post" action="{{ route('admin.properties.availability.update-range', $property) }}" class="mt-4 grid gap-4">
                @csrf
                <div class="flex flex-wrap gap-3">
                    <label class="field-label w-44">From
                        <input type="date" name="date_from" value="{{ old('date_from') }}" min="{{ now()->toDateString() }}" class="input" required>
                    </label>
                    <label class="field-label w-44">To
                        <input type="date" name="date_to" value="{{ old('date_to') }}" min="{{ now()->toDateString() }}" class="input" required>
                    </label>
                    <label class="field-label w-32">Rate
                        <input type="number" name="rate" value="{{ old('rate') }}" min="0.01" step="0.01" class="input" placeholder="No change">
                    </label>
                </div>
                <div class="flex flex-wrap gap-3">
                    <label class="field-label w-36">Min stay (arrival)
                        <input type="number" name="min_stay_arrival" value="{{ old('min_stay_arrival') }}" min="1" max="365" class="input" placeholder="No change">
                    </label>
                    <label class="field-label w-36">Min stay (through)
                        <input type="number" name="min_stay_through" value="{{ old('min_stay_through') }}" min="1" max="365" class="input" placeholder="No change">
                    </label>
                    <label class="field-label w-36">Max stay
                        <input type="number" name="max_stay" value="{{ old('max_stay') }}" min="1" max="365" class="input" placeholder="No change">
                    </label>
                </div>
                <div class="flex flex-wrap gap-3">
                    @foreach(['stop_sell' => 'Stop sell', 'closed_to_arrival' => 'Closed to arrival', 'closed_to_departure' => 'Closed to departure'] as $field => $label)
                        <label class="field-label w-44">{{ $label }}
                            <select name="{{ $field }}" class="input">
                                <option value="">No change</option>
                                <option value="1" @selected(old($field) === '1')>On</option>
                                <option value="0" @selected(old($field) === '0')>Off</option>
                            </select>
                        </label>
                    @endforeach
                </div>
                <div><button type="submit" class="btn-primary">Save rates and restrictions</button></div>
            </form>
            <form method="post" action="{{ route('admin.properties.availability.release-manual', $property) }}" class="mt-3" onsubmit="return confirm('Hand every manually set night back to PriceLabs? Its next pull will overwrite them.')">
                @csrf
                <button type="submit" class="btn-secondary">Release to PriceLabs</button>
                <span class="section-copy ml-2">Nights you set here are protected from PriceLabs until you release them.</span>
            </form>
        </section>

        {{-- Channex push log --}}
        <details class="card card-pad">
            <summary class="section-title cursor-pointer">Channex push log</summary>
            <p class="section-copy mt-2">The last 30 updates sent to Channex. The task ID is what Channex asks for in the certification form.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-slate-500">
                        <tr><th class="py-1 pr-4">Time</th><th class="py-1 pr-4">Type</th><th class="py-1 pr-4">Ranges</th><th class="py-1 pr-4">Result</th><th class="py-1">Task ID</th></tr>
                    </thead>
                    <tbody>
                        @forelse($pushLog as $row)
                            <tr class="border-t border-slate-200 align-top">
                                <td class="py-1 pr-4 whitespace-nowrap">{{ \Carbon\Carbon::parse($row->created_at)->format('M j, g:i:s a') }}</td>
                                <td class="py-1 pr-4">{{ $row->path }}</td>
                                <td class="py-1 pr-4">{{ $row->value_count }}</td>
                                <td class="py-1 pr-4 {{ $row->success ? 'text-emerald-700' : 'text-red-700' }}">{{ $row->success ? 'Sent' : 'Failed ('.$row->status.')' }}</td>
                                <td class="py-1 font-mono text-xs break-all">{{ $row->task_ids ?: ($row->error ?: '') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-2 text-slate-500">Nothing sent yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>

        {{-- Settings --}}
        <details class="card card-pad" @if(session('mappingOptions') || session('ratePlanOptions')) open @endif>
            <summary class="section-title cursor-pointer">Settings</summary>

            <div class="mt-5 grid gap-8">

                {{-- Calendar link --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Calendar link (iCal)</h3>
                    <p class="section-copy">Import blocked and booked dates from an iCal feed. Use this for the first import, or to re-check a calendar.</p>

                    <form method="post" action="{{ route('admin.properties.availability.save-ical-url', $property) }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="field-label flex-1 min-w-[280px]">iCal URL
                            <input type="url" name="airbnb_ical_url" value="{{ old('airbnb_ical_url', $property->airbnb_ical_url) }}" placeholder="https://.../calendar.ics" class="input">
                        </label>
                        <button type="submit" class="btn-secondary">Save link</button>
                    </form>

                    <form method="post" action="{{ route('admin.properties.availability.import-ical', $property) }}" class="mt-3" x-data="{ ok: {{ $seeded ? 'false' : 'true' }} }">
                        @csrf
                        @if($seeded)
                            <label class="field-help mb-2 flex items-center gap-2"><input type="checkbox" name="reseed" value="1" x-model="ok"> Re-import (replaces the dates currently stored)</label>
                        @endif
                        <label class="field-help mb-2 flex items-center gap-2"><input type="checkbox" name="confirm_high_block" value="1"> Import even if most dates look blocked</label>
                        <button type="submit" class="btn-primary" :disabled="{{ $property->airbnb_ical_url ? '!ok' : 'true' }}">Import from calendar link</button>
                        @unless($property->airbnb_ical_url)
                            <span class="field-help ml-2">Save a link above first.</span>
                        @endunless
                    </form>
                </div>

                {{-- Channex room type --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Channex room type</h3>
                    <p class="section-copy">Required to send availability to Channex.</p>
                    <div class="mt-3 flex items-center gap-3">
                        <span class="text-sm {{ $isMapped ? 'text-emerald-700' : 'text-amber-700' }} font-medium">{{ $isMapped ? 'Mapped' : 'Not mapped yet' }}</span>
                        @if($isMapped)
                            <span class="field-help">Room type ID: {{ $property->channex_room_type_id }}</span>
                        @endif
                    </div>

                    <form method="post" action="{{ route('admin.properties.availability.fetch-mapping', $property) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn-secondary" {{ $property->channex_property_id ? '' : 'disabled' }}>Fetch room types from Channex</button>
                        @unless($property->channex_property_id)
                            <span class="field-help ml-2">Set the Channex Property ID on the main property form first.</span>
                        @endunless
                    </form>

                    @if(session('mappingOptions'))
                        <form method="post" action="{{ route('admin.properties.availability.save-mapping', $property) }}" class="mt-3 grid gap-3">
                            @csrf
                            <label class="field-label">Choose the room type
                                <select name="channex_room_type_id" class="input">
                                    <option value="">— Select —</option>
                                    @foreach(session('mappingOptions') as $option)
                                        <option value="{{ $option['room_type_id'] }}" {{ $property->channex_room_type_id === $option['room_type_id'] ? 'selected' : '' }}>{{ $option['room_type_title'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="btn-primary w-fit">Save mapping</button>
                        </form>
                    @endif
                </div>

                {{-- Rate plan --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Rate plan</h3>
                    <p class="section-copy">Rates and minimum stays come from PriceLabs. Choose the Channex rate plan they are sent to.</p>

                    <form method="post" action="{{ route('admin.properties.availability.fetch-rate-plans', $property) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn-secondary" {{ $isMapped ? '' : 'disabled' }}>Fetch rate plans from Channex</button>
                        @unless($isMapped)
                            <span class="field-help ml-2">Map the room type first.</span>
                        @endunless
                        @if($property->channex_rate_plan_id)
                            <span class="field-help ml-2">Rate plan ID: {{ $property->channex_rate_plan_id }}</span>
                        @endif
                    </form>

                    @if(session('ratePlanOptions'))
                        <form method="post" action="{{ route('admin.properties.availability.save-rate-settings', $property) }}" class="mt-3 grid gap-3">
                            @csrf
                            <label class="field-label">Rate plan
                                <select name="channex_rate_plan_id" class="input">
                                    <option value="">— Select —</option>
                                    @foreach(session('ratePlanOptions') as $option)
                                        <option value="{{ $option['rate_plan_id'] }}" {{ $property->channex_rate_plan_id === $option['rate_plan_id'] ? 'selected' : '' }}>{{ $option['rate_plan_title'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="submit" class="btn-primary w-fit">Save rate plan</button>
                        </form>
                    @endif
                </div>

                {{-- Booking window --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Booking window</h3>
                    <p class="section-copy">Close dates more than this many days ahead. Leave blank to manage by hand. Dates you open yourself stay open, and your manual blocks are never changed.</p>
                    <form method="post" action="{{ route('admin.properties.availability.save-window', $property) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="field-label w-48">Days ahead
                            <input type="number" name="close_ahead_days" min="1" max="499" value="{{ old('close_ahead_days', $property->close_ahead_days) }}" placeholder="Off" class="input">
                        </label>
                        <button type="submit" class="btn-secondary">Save</button>
                    </form>
                </div>

                {{-- PriceLabs --}}
                <div>
                    <h3 class="font-semibold text-slate-900">PriceLabs rates</h3>
                    <p class="section-copy">Rates and minimum stays are pulled from PriceLabs every 6 hours and sent to Channex as rate-only updates.</p>

                    

                    <form method="post" action="{{ route('admin.properties.availability.save-pricelabs', $property) }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <label class="field-label min-w-[200px] flex-1">PriceLabs listing ID
                            <input type="text" name="pricelabs_listing_id" value="{{ old('pricelabs_listing_id', $property->pricelabs_listing_id) }}" class="input" required>
                        </label>
                        <label class="field-label min-w-[160px]">PMS name in PriceLabs
                            <input type="text" name="pricelabs_pms" value="{{ old('pricelabs_pms', $property->pricelabs_pms) }}" class="input" required>
                        </label>
                        <button type="submit" class="btn-secondary">Save</button>
                    </form>

                    <form method="post" action="{{ route('admin.properties.availability.sync-pricelabs', $property) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="btn-primary" {{ ($priceLabsKeySet && $property->pricelabs_listing_id && $property->channex_rate_plan_id) ? '' : 'disabled' }}>Pull rates now</button>
                        @if($property->pricelabs_synced_at)
                            <span class="field-help ml-2">Last pull {{ \Carbon\Carbon::parse($property->pricelabs_synced_at)->diffForHumans() }}</span>
                        @endif
                    </form>
                </div>

                {{-- Sync tools --}}
                <div>
                    <h3 class="font-semibold text-slate-900">Sync to Channex</h3>
                    <p class="section-copy">Full sync sends the next 500 days of availability and rates in one call each. Use it for certification or to repair a mismatch.</p>

                    <form method="post" action="{{ route('admin.properties.availability.full-sync', $property) }}" class="mt-3" onsubmit="return confirm('Send the next 500 days to Channex now?');">
                        @csrf
                        <button type="submit" class="btn-primary" {{ $isMapped ? '' : 'disabled' }}>Run full sync</button>
                        @unless($isMapped)
                            <span class="field-help ml-2">Map the Channex room type first.</span>
                        @endunless
                    </form>

                    <form method="post" action="{{ route('admin.properties.availability.push-to-channex', $property) }}" class="mt-4" x-data="{ ok: {{ $seeded ? 'false' : 'true' }} }">
                        @csrf
                        @if($seeded)
                            <label class="field-help mb-2 flex items-center gap-2"><input type="checkbox" name="reseed" value="1" x-model="ok"> Overwrite Channex's current dates, including any bookings made since</label>
                        @endif
                        <button type="submit" class="btn-secondary" :disabled="{{ ($isMapped && $availabilities->isNotEmpty()) ? '!ok' : 'true' }}">Initial push of stored dates</button>
                        <span class="field-help ml-2">First-time setup only.</span>
                    </form>
                </div>

            </div>
        </details>
    </div>

    <script>
        function availCal(days, today) {
            const t = new Date(today + 'T00:00:00');
            const pad = n => String(n).padStart(2, '0');
            const fmt = (y, m, d) => y + '-' + pad(m + 1) + '-' + pad(d);
            return {
                days, today, y: t.getFullYear(), m: t.getMonth(), start: null, end: null,
                title() { return new Date(this.y, this.m, 1).toLocaleString('en-US', { month: 'long', year: 'numeric' }); },
                canPrev() { return this.y > t.getFullYear() || this.m > t.getMonth(); },
                prev() { if (!this.canPrev()) return; this.m--; if (this.m < 0) { this.m = 11; this.y--; } },
                next() { this.m++; if (this.m > 11) { this.m = 0; this.y++; } },
                cells() {
                    const out = [], lead = new Date(this.y, this.m, 1).getDay(), n = new Date(this.y, this.m + 1, 0).getDate();
                    for (let i = 0; i < lead; i++) out.push({ key: 'b' + i, blank: true });
                    for (let d = 1; d <= n; d++) {
                        const date = fmt(this.y, this.m, d), row = this.days[date] || null;
                        out.push({ key: date, blank: false, day: d, date, past: date < this.today, state: row ? row.s : null, rate: row ? row.r : null });
                    }
                    return out;
                },
                cls(c) {
                    if (c.state === 'booked') return 'bg-violet-300 text-violet-950 font-semibold';
                    if (c.state === 'blocked') return 'bg-red-100 text-red-900';
                    if (c.state === 'open') return 'bg-emerald-100 text-emerald-900';
                    return 'bg-slate-50 text-slate-500';
                },
                pick(d) {
                    if (!this.start || this.end) { this.start = d; this.end = null; return; }
                    if (d < this.start) { this.end = this.start; this.start = d; } else { this.end = d; }
                },
                picked(d) { return this.start && (this.end ? d >= this.start && d <= this.end : d === this.start); },
                clear() { this.start = null; this.end = null; },
                go(mode, e) { const f = e.target.closest('form'); f.querySelector('[name=availability]').value = mode; f.submit(); },
                label() {
                    const f = s => new Date(s + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    return this.end && this.end !== this.start ? f(this.start) + ' to ' + f(this.end) + ' (last night)' : f(this.start);
                },
            };
        }
    </script>
</x-admin-layout>
