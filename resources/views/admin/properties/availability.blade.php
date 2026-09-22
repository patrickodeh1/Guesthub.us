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

            <form method="post" action="{{ route('admin.properties.availability.import-ical', $property) }}" class="mt-3">
                @csrf
                <button type="submit" class="btn-primary" {{ $property->airbnb_ical_url ? '' : 'disabled' }}>Import from Airbnb</button>
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
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($availabilities as $row)
                                <tr class="border-t border-slate-100">
                                    <td class="py-1.5 px-3">{{ $row->date->format('D, M j Y') }}</td>
                                    <td class="py-1.5 px-3">
                                        @if($row->is_available)
                                            <span class="text-emerald-700">Available</span>
                                        @else
                                            <span class="text-red-700">Blocked</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="field-help mt-3">Nothing imported yet.</p>
            @endif

            <form method="post" action="{{ route('admin.properties.availability.push-to-channex', $property) }}" class="mt-4">
                @csrf
                <button type="submit" class="btn-primary" {{ ($isMapped && $availabilities->isNotEmpty()) ? '' : 'disabled' }}>Push to Channex</button>
                @unless($isMapped)
                    <span class="field-help ml-2">Set the Channex mapping above first.</span>
                @endunless
            </form>
        </section>

    </div>
</x-admin-layout>
