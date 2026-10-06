<x-admin-layout :title="$property->name.' - Airbnb Availability'">
    <div class="page-header">
        <div>
            <p class="eyebrow">Property setup</p>
            <h1 class="page-title">Airbnb Availability</h1>
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

    @php($seeded = (bool) $property->channex_availability_seeded_at)

    @if($seeded)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Seeded to Channex on {{ $property->channex_availability_seeded_at->format('M j, Y g:i A') }}. Channex is now the source of truth, so make date changes there. Import and push are locked unless you tick "re-seed".
        </div>
    @endif

    <div class="grid gap-6">

        {{-- ── Airbnb iCal import ──────────────────────────────────────────── --}}
        <section class="card card-pad">
            <h2 class="section-title">Import from Airbnb</h2>
            <p class="section-copy">Pulls Airbnb's current blocked dates. Run this whenever Airbnb's calendar changes and you need Channex to reflect it.</p>

            <form method="post" action="{{ route('admin.properties.availability.save-ical-url', $property) }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <label class="field-label flex-1 min-w-[280px]">Airbnb iCal export URL
                    <input type="url" name="airbnb_ical_url" value="{{ old('airbnb_ical_url', $property->airbnb_ical_url) }}" placeholder="https://www.airbnb.com/calendar/ical/xxxxx.ics?t=xxxxx" class="input">
                </label>
                <button type="submit" class="btn-secondary">Save URL</button>
            </form>

            <form method="post" action="{{ route('admin.properties.availability.import-ical', $property) }}" class="mt-3" x-data="{ ok: {{ $seeded ? 'false' : 'true' }} }">
                @csrf
                @if($seeded)
                    <label class="field-help mb-2 flex items-center gap-2"><input type="checkbox" name="reseed" value="1" x-model="ok"> Re-seed (overwrites what Channex has with Airbnb's current feed)</label>
                @endif
                <label class="field-help mb-2 flex items-center gap-2"><input type="checkbox" name="confirm_high_block" value="1"> Import even if most dates look blocked</label>
                <button type="submit" class="btn-primary" :disabled="{{ $property->airbnb_ical_url ? '!ok' : 'true' }}">Import from Airbnb</button>
                @unless($property->airbnb_ical_url)
                    <span class="field-help ml-2">Save a URL above first.</span>
                @endunless
            </form>
        </section>

        {{-- ── Channex mapping ─────────────────────────────────────────────── --}}
        <section class="card card-pad">
            <h2 class="section-title">Channex room type</h2>
            <p class="section-copy">Required to push to Channex.</p>

            <div class="mt-4 flex items-center gap-3">
                <span class="text-sm {{ $isMapped ? 'text-emerald-700' : 'text-amber-700' }} font-medium">
                    {{ $isMapped ? 'Mapped' : 'Not mapped yet' }}
                </span>
                @if($isMapped)
                    <span class="field-help">Room type ID: {{ $property->channex_room_type_id }}</span>
                @endif
            </div>

            <form method="post" action="{{ route('admin.properties.availability.fetch-mapping', $property) }}" class="mt-4">
                @csrf
                <button type="submit" class="btn-secondary" {{ $property->channex_property_id ? '' : 'disabled' }}>Fetch room types from Channex</button>
                @unless($property->channex_property_id)
                    <span class="field-help ml-2">Set the Channex Property ID on the main property form first.</span>
                @endunless
            </form>

            @if(session('mappingOptions'))
                <form method="post" action="{{ route('admin.properties.availability.save-mapping', $property) }}" class="mt-4 grid gap-3">
                    @csrf
                    <label class="field-label">Choose the room type
                        <select name="channex_room_type_id" class="input">
                            <option value="">— Select —</option>
                            @foreach(session('mappingOptions') as $option)
                                <option value="{{ $option['room_type_id'] }}" {{ $property->channex_room_type_id === $option['room_type_id'] ? 'selected' : '' }}>
                                    {{ $option['room_type_title'] }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <button type="submit" class="btn-primary w-fit">Save mapping</button>
                </form>
            @endif
        </section>

        {{-- ── Current imported data + push ────────────────────────────────── --}}
        {{-- ── Rates & restrictions ────────────────────────────────────────── --}}
        @php($rateOn = $property->rate_source === 'guesthub')
        @php($canRate = $rateOn && $property->channex_rate_plan_id)
        <section class="card card-pad">
            <h2 class="section-title">Rates &amp; restrictions</h2>
            <p class="section-copy">Nightly rate and stay rules only. These are never linked to booking fees, parking, deposits or any guest charge.</p>

            @if($rateOn)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    Guesthub controls rates for this property. Make sure PriceLabs (or any other rate tool) is switched off for it in Channex, otherwise the two will overwrite each other.
                </div>
            @endif

            <form method="post" action="{{ route('admin.properties.availability.fetch-rate-plans', $property) }}" class="mt-4">
                @csrf
                <button type="submit" class="btn-secondary" {{ $isMapped ? '' : 'disabled' }}>Fetch rate plans from Channex</button>
                @unless($isMapped)
                    <span class="field-help ml-2">Map the room type first.</span>
                @endunless
                @if($property->channex_rate_plan_id)
                    <span class="field-help ml-2">Rate plan ID: {{ $property->channex_rate_plan_id }}</span>
                @endif
            </form>

            <form method="post" action="{{ route('admin.properties.availability.save-rate-settings', $property) }}" class="mt-4 grid gap-3">
                @csrf
                @if(session('ratePlanOptions'))
                    <label class="field-label">Rate plan
                        <select name="channex_rate_plan_id" class="input">
                            <option value="">— Select —</option>
                            @foreach(session('ratePlanOptions') as $option)
                                <option value="{{ $option['rate_plan_id'] }}" {{ $property->channex_rate_plan_id === $option['rate_plan_id'] ? 'selected' : '' }}>{{ $option['rate_plan_title'] }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="field-label">Who controls rates
                    <select name="rate_source" class="input">
                        <option value="external" {{ $property->rate_source !== 'guesthub' ? 'selected' : '' }}>External tool (e.g. PriceLabs). Guesthub sends no rates</option>
                        <option value="guesthub" {{ $rateOn ? 'selected' : '' }}>Guesthub</option>
                    </select>
                </label>
                <button type="submit" class="btn-primary w-fit">Save rate settings</button>
            </form>
        </section>

        <section class="card card-pad">
            <h2 class="section-title">Edit dates</h2>
            <p class="section-copy">Applies to every night from the first date to the last date. Blank fields are left unchanged. Changes are sent to Channex within about a minute.</p>

            <form method="post" action="{{ route('admin.properties.availability.update-range', $property) }}" class="mt-4 grid gap-4">
                @csrf
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="field-label">From
                        <input type="date" name="date_from" value="{{ old('date_from') }}" min="{{ now()->toDateString() }}" required class="input">
                    </label>
                    <label class="field-label">To (last night)
                        <input type="date" name="date_to" value="{{ old('date_to') }}" min="{{ now()->toDateString() }}" required class="input">
                    </label>
                </div>

                <label class="field-label">Availability
                    <select name="availability" class="input">
                        <option value="">No change</option>
                        <option value="block">Block these nights</option>
                        <option value="open">Open these nights</option>
                    </select>
                </label>

                <fieldset @disabled(! $canRate) class="grid gap-3 sm:grid-cols-2 {{ $canRate ? '' : 'opacity-60' }}">
                    <label class="field-label">Nightly rate
                        <input type="number" step="0.01" min="0.01" name="rate" value="{{ old('rate') }}" class="input" placeholder="e.g. 250.00">
                    </label>
                    <label class="field-label">Min stay (arrival)
                        <input type="number" min="1" max="365" name="min_stay_arrival" value="{{ old('min_stay_arrival') }}" class="input">
                    </label>
                    <label class="field-label">Min stay (through)
                        <input type="number" min="1" max="365" name="min_stay_through" value="{{ old('min_stay_through') }}" class="input">
                    </label>
                    <label class="field-label">Max stay
                        <input type="number" min="1" max="365" name="max_stay" value="{{ old('max_stay') }}" class="input">
                    </label>
                    <label class="field-label">Stop sell
                        <select name="stop_sell" class="input"><option value="">No change</option><option value="1">On</option><option value="0">Off</option></select>
                    </label>
                    <label class="field-label">Closed to arrival
                        <select name="closed_to_arrival" class="input"><option value="">No change</option><option value="1">On</option><option value="0">Off</option></select>
                    </label>
                    <label class="field-label">Closed to departure
                        <select name="closed_to_departure" class="input"><option value="">No change</option><option value="1">On</option><option value="0">Off</option></select>
                    </label>
                </fieldset>
                @unless($canRate)
                    <p class="field-help">Rate and stay fields unlock once the rate source is Guesthub and a rate plan is chosen above.</p>
                @endunless

                <button type="submit" class="btn-primary w-fit">Apply to dates</button>
            </form>
        </section>

        {{-- ── Full sync ───────────────────────────────────────────────────── --}}
        <section class="card card-pad">
            <h2 class="section-title">Full sync to Channex</h2>
            <p class="section-copy">Sends the next 500 days of availability, and of rates and restrictions when Guesthub controls rates, as one call each. Use it for certification or to repair a drift. Nothing here involves booking fees or charges.</p>
            <form method="post" action="{{ route('admin.properties.availability.full-sync', $property) }}" class="mt-4" onsubmit="return confirm('Send the next 500 days to Channex now?');">
                @csrf
                <button type="submit" class="btn-primary" {{ $isMapped ? '' : 'disabled' }}>Run full sync</button>
                @unless($isMapped)
                    <span class="field-help ml-2">Map the Channex room type first.</span>
                @endunless
            </form>
        </section>

        <section class="card card-pad">
            <h2 class="section-title">Current Airbnb availability</h2>
            <p class="section-copy">{{ $availabilities->count() }} date(s) imported &middot; {{ $blockedCount }} blocked.</p>

            @if($availabilities->isNotEmpty())
                <div class="mt-4 max-h-80 overflow-y-auto rounded-lg border border-slate-200">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50">
                            <tr class="text-left text-xs font-bold uppercase tracking-wide text-slate-500">
                                <th class="py-2 px-3">Date</th>
                                <th class="py-2 px-3">Status</th>
                                <th class="py-2 px-3">Rate</th>
                                <th class="py-2 px-3">Stay rules</th>
                                <th class="py-2 px-3">Flags</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($availabilities as $row)
                                <tr class="border-t border-slate-100">
                                    <td class="py-1.5 px-3">{{ $row->date->format('D, M j Y') }}</td>
                                    <td class="py-1.5 px-3">
                                        @if($row->status === 'booked')
                                            <span class="text-sky-700">Booked</span>
                                        @elseif($row->is_available)
                                            <span class="text-emerald-700">Available</span>
                                        @else
                                            <span class="text-red-700">Blocked</span>
                                        @endif
                                    </td>
                                    <td class="py-1.5 px-3">{{ $row->rate !== null ? '$'.number_format((float) $row->rate, 2) : '—' }}</td>
                                    <td class="py-1.5 px-3">{{ collect([$row->min_stay_arrival ? 'min '.$row->min_stay_arrival : null, $row->min_stay_through ? 'thru '.$row->min_stay_through : null, $row->max_stay ? 'max '.$row->max_stay : null])->filter()->implode(' · ') ?: '—' }}</td>
                                    <td class="py-1.5 px-3">{{ collect([$row->stop_sell ? 'Stop sell' : null, $row->closed_to_arrival ? 'CTA' : null, $row->closed_to_departure ? 'CTD' : null])->filter()->implode(', ') ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="field-help mt-3">Nothing imported yet.</p>
            @endif

            <form method="post" action="{{ route('admin.properties.availability.push-to-channex', $property) }}" class="mt-4" x-data="{ ok: {{ $seeded ? 'false' : 'true' }} }">
                @csrf
                @if($seeded)
                    <label class="field-help mb-2 flex items-center gap-2"><input type="checkbox" name="reseed" value="1" x-model="ok"> Re-seed (overwrites Channex's current dates, including any bookings made since)</label>
                @endif
                <button type="submit" class="btn-primary" :disabled="{{ ($isMapped && $availabilities->isNotEmpty()) ? '!ok' : 'true' }}">Push to Channex</button>
                @unless($isMapped)
                    <span class="field-help ml-2">Set the Channex mapping above first.</span>
                @endunless
            </form>
        </section>

    </div>
</x-admin-layout>
