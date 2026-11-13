@php
    $cleanerLayout = ! (auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company', 'manager']));
@endphp
<x-dynamic-component :component="$cleanerLayout ? 'cleaner-layout' : 'app-layout'">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Cleaning checklist</p>
                <h2 class="text-xl font-semibold text-gray-900">Guest supplies</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $property->name }} · {{ $session->scheduled_date->format('M j, Y') }}</p>
            </div>
            <a href="{{ route('sessions.show', $session) }}" class="btn-secondary">Back to cleaning</a>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mx-auto max-w-3xl space-y-5 px-1">
        <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <h3 class="font-semibold text-slate-900">How this works</h3>
            <ol class="mt-3 space-y-2 text-sm text-slate-600">
                <li><strong>1. Count what is already there.</strong> Check the guest area before adding anything.</li>
                <li><strong>2. Record what you take.</strong> Count the amount taken from the supply closet or store.</li>
                <li><strong>3. Save the count.</strong> The system updates both locations and records the cleaning.</li>
            </ol>
            <p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-600">You can enter partial amounts: 0.5 = half a bottle or roll; 0.25 = one quarter. Use the unit shown on each item.</p>
        </section>

        @if($items->isEmpty())
            <section class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
                <p class="font-semibold text-slate-900">No guest supplies set up</p>
                <p class="mt-1 text-sm text-slate-500">Ask your property manager to add guest or cleaning supplies and stock locations.</p>
            </section>
        @elseif($locations->isEmpty())
            <section class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-center">
                <p class="font-semibold text-slate-900">Stock locations are not set up</p>
                <p class="mt-1 text-sm text-slate-500">Ask your property manager to create a supply closet and guest-area locations before recording stock.</p>
            </section>
        @else
            @foreach($items as $item)
                @php
                    $record = $records->firstWhere('inventory_item_id', $item->id);
                    $guestLocation = $record?->guestLocation;
                    $sourceLocation = $record?->fromLocation;
                    $guestBalance = $guestLocation ? (float) ($item->stocks->firstWhere('inventory_location_id', $guestLocation->id)?->quantity ?? 0) : null;
                @endphp
                <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-100 p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-900">{{ $item->name }}</h3>
                                <p class="mt-1 text-xs text-slate-500">{{ ucfirst(str_replace('_', ' ', $item->category)) }}@if($item->unit_of_measure) · Measured in {{ $item->unit_of_measure }}@endif</p>
                            </div>
                            @if($record)<span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Recorded</span>@else<span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">Not recorded</span>@endif
                        </div>
                        @if($guestLocation)
                            <p class="mt-3 text-xs text-slate-500">Current tracked guest-area balance · {{ $guestLocation->name }}: <strong>{{ number_format($guestBalance, 2) }} {{ $item->unit_of_measure }}</strong></p>
                        @endif
                    </div>
                    <form method="post" action="{{ route('sessions.inventory.record', $session) }}" class="space-y-4 p-4 sm:p-5">
                        @csrf
                        <input type="hidden" name="item_id" value="{{ $item->id }}">
                        <div>
                            <label class="block text-sm font-semibold text-slate-800">1. How much is currently in the guest area?</label>
                            <p class="mt-1 text-xs text-slate-500">Count what you see before replenishing. Enter zero if it is empty.</p>
                            <div class="mt-2 flex items-center gap-2">
                                <input type="number" name="quantity_available" min="0" max="99999999" step="0.001" required value="{{ old('item_id') == $item->id ? old('quantity_available') : ($record?->quantity_available ?? '') }}" class="input min-w-0 flex-1 text-lg" placeholder="e.g. 0.5">
                                <span class="text-sm text-slate-500">{{ $item->unit_of_measure ?: 'units' }}</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-800">2. How much are you taking to refill it?</label>
                            <p class="mt-1 text-xs text-slate-500">Only count what you take from storage now. Enter zero if no refill is needed.</p>
                            <div class="mt-2 flex items-center gap-2">
                                <input type="number" name="quantity_taken" min="0" max="99999999" step="0.001" required value="{{ old('item_id') == $item->id ? old('quantity_taken', 0) : ($record?->quantity_taken ?? 0) }}" class="input min-w-0 flex-1 text-lg">
                                <span class="text-sm text-slate-500">{{ $item->unit_of_measure ?: 'units' }}</span>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block text-sm font-medium text-slate-700">Take stock from
                                <select name="from_location_id" class="input mt-1 w-full">
                                    <option value="">Choose storage location</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old('from_location_id', $sourceLocation?->id ?? $item->default_location_id) == $location->id)>{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-sm font-medium text-slate-700">Guest area being refilled
                                <select name="guest_location_id" required class="input mt-1 w-full">
                                    <option value="">Choose guest area</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old('guest_location_id', $guestLocation?->id) == $location->id)>{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label class="block text-sm font-medium text-slate-700">Note (optional)
                            <textarea name="notes" rows="2" maxlength="2000" class="input mt-1 w-full" placeholder="For example: one bottle was damaged">{{ old('item_id') == $item->id ? old('notes') : ($record?->notes ?? '') }}</textarea>
                        </label>
                        <div class="flex flex-col gap-2 border-t border-slate-100 pt-3 sm:flex-row sm:items-center sm:justify-between">
                            @if($record)
                                <p class="text-xs text-slate-500">Last saved {{ $record->updated_at->format('M j, g:i A') }} · After refill: {{ number_format((float) $record->quantity_after_restock, 2) }} {{ $item->unit_of_measure }}</p>
                            @else
                                <p class="text-xs text-slate-500">Your count is saved against this cleaning session.</p>
                            @endif
                            <button class="btn-primary w-full sm:w-auto">Save {{ $record ? 'changes' : 'count' }}</button>
                        </div>
                    </form>
                </section>
            @endforeach
        @endif

        @if($records->isNotEmpty())
            <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
                <h3 class="font-semibold text-slate-900">Recorded during this cleaning</h3>
                <div class="mt-3 divide-y divide-slate-100">
                    @foreach($records as $record)
                        <div class="py-3">
                            <p class="font-medium text-slate-900">{{ $record->item->name }}</p>
                            <p class="mt-1 text-sm text-slate-600">Before refill: {{ number_format((float) $record->quantity_available, 2) }} · Taken: {{ number_format((float) $record->quantity_taken, 2) }} · After: {{ number_format((float) $record->quantity_after_restock, 2) }} {{ $record->item->unit_of_measure }}</p>
                            @if($record->quantity_guest_consumed !== null)<p class="text-xs text-slate-500">Estimated guest use since last cleaning: {{ number_format((float) $record->quantity_guest_consumed, 2) }} {{ $record->item->unit_of_measure }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-dynamic-component>