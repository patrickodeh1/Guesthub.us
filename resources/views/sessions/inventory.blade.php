@php
    $cleanerLayout = ! (auth()->check() && auth()->user()->hasAnyRole(['admin', 'owner', 'company', 'manager']));
@endphp
<x-dynamic-component :component="$cleanerLayout ? 'cleaner-layout' : 'app-layout'">
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
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

    <div class="mx-auto max-w-4xl space-y-5">
        <section class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-lg font-semibold text-gray-900">Record guest-area stock</h3>
            <p class="mt-1 text-sm text-gray-500">Count what is already in the guest area before replenishing it, then record what you take from the supply closet. Partial quantities are supported.</p>

            @if($items->isEmpty() || $locations->isEmpty())
                <p class="mt-4 rounded-lg bg-gray-50 p-4 text-sm text-gray-600">Inventory items and stock locations have not been set up for this property yet. Ask the property manager to add them first.</p>
            @else
                <form method="post" action="{{ route('sessions.inventory.record', $session) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <label class="block text-sm font-medium text-gray-700 sm:col-span-2">Item
                        <select name="item_id" required class="input mt-1 w-full">
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}@if($item->unit_of_measure) ({{ $item->unit_of_measure }})@endif</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700">Current amount in guest area
                        <input type="number" name="quantity_available" min="0" max="99999999" step="0.001" required class="input mt-1 w-full" placeholder="e.g. 0.5">
                    </label>
                    <label class="block text-sm font-medium text-gray-700">Amount taken to replenish
                        <input type="number" name="quantity_taken" min="0" max="99999999" step="0.001" required class="input mt-1 w-full" value="0">
                    </label>
                    <label class="block text-sm font-medium text-gray-700">Take stock from
                        <select name="from_location_id" class="input mt-1 w-full">
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700">Guest area / destination
                        <select name="guest_location_id" required class="input mt-1 w-full">
                            <option value="">Choose guest area</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm font-medium text-gray-700 sm:col-span-2">Notes (optional)
                        <textarea name="notes" rows="2" maxlength="2000" class="input mt-1 w-full" placeholder="Anything unusual, damaged or missing"></textarea>
                    </label>
                    <div class="sm:col-span-2"><button class="btn-primary">Save stock count</button></div>
                </form>
            @endif
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5">
            <h3 class="text-lg font-semibold text-gray-900">Recorded for this cleaning</h3>
            <div class="mt-3 divide-y divide-gray-100">
                @forelse($records as $record)
                    <div class="flex flex-wrap items-start justify-between gap-3 py-3">
                        <div>
                            <p class="font-medium text-gray-900">{{ $record->item->name }}</p>
                            <p class="mt-1 text-sm text-gray-500">Before restock: {{ number_format((float) $record->quantity_available, 3) }} · Taken: {{ number_format((float) $record->quantity_taken, 3) }} · After: {{ number_format((float) $record->quantity_after_restock, 3) }}</p>
                            @if($record->quantity_guest_consumed !== null)
                                <p class="text-sm text-gray-500">Estimated guest use since previous cleaning: {{ number_format((float) $record->quantity_guest_consumed, 3) }}</p>
                            @endif
                            <p class="text-xs text-gray-400">{{ $record->fromLocation?->name ?? 'No stock taken' }} → {{ $record->guestLocation?->name ?? 'Location removed' }}</p>
                            @if($record->notes)<p class="mt-1 text-sm text-gray-500">{{ $record->notes }}</p>@endif
                        </div>
                        <span class="text-xs text-gray-400">{{ $record->updated_at->format('M j, g:i A') }}</span>
                    </div>
                @empty
                    <p class="py-5 text-sm text-gray-500">No guest supplies have been recorded for this cleaning yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-dynamic-component>
