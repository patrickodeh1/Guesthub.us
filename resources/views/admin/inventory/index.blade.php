<x-admin-layout title="Property inventory">
    <div class="page-header">
        <div>
            <p class="eyebrow">Operations</p>
            <h1 class="page-title">Inventory</h1>
            <p class="page-subtitle">Track property assets, guest supplies and cleaning stock by location.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    @if($properties->isEmpty())
        <div class="card card-pad text-sm text-slate-500">No properties are available for your account.</div>
    @else
        <form method="get" class="card card-pad mb-5 flex flex-wrap items-end gap-3">
            <label class="field-label min-w-64">Property
                <select name="property_id" class="input" onchange="this.form.submit()">
                    @foreach($properties as $option)
                        <option value="{{ $option->id }}" @selected($property && $property->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <span class="text-sm text-slate-500">Inventory is isolated per property.</span>
        </form>

        @if($property)
            @php
                $totalItems = $items->count();
                $lowItems = $items->filter(fn ($item) => $item->is_low_stock);
                $totalUnits = $items->sum(fn ($item) => (float) $item->quantity_on_hand);
            @endphp
            <div class="mb-5 grid gap-3 sm:grid-cols-3">
                <div class="card card-pad"><p class="text-sm text-slate-500">Active items</p><p class="mt-1 text-2xl font-semibold">{{ $totalItems }}</p></div>
                <div class="card card-pad"><p class="text-sm text-slate-500">Low stock</p><p class="mt-1 text-2xl font-semibold {{ $lowItems->isNotEmpty() ? 'text-amber-700' : '' }}">{{ $lowItems->count() }}</p></div>
                <div class="card card-pad"><p class="text-sm text-slate-500">Units across locations</p><p class="mt-1 text-2xl font-semibold">{{ number_format($totalUnits, 3) }}</p></div>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
                <section class="card card-pad">
                    <h2 class="section-title">Add inventory item</h2>
                    <form method="post" enctype="multipart/form-data" action="{{ route('admin.inventory.items.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                        @csrf
                        <input type="hidden" name="property_id" value="{{ $property->id }}">
                        <label class="field-label sm:col-span-2">Item name<input class="input" name="name" required maxlength="160" placeholder="e.g. Bath towels"></label>
                        <label class="field-label">Category<select class="input" name="category" required><option value="guest_supply">Guest supply</option><option value="cleaning_supply">Cleaning supply</option><option value="asset">Fixed asset</option><option value="other">Other</option></select></label>
                        <label class="field-label">Unit of measure<input class="input" name="unit_of_measure" maxlength="40" placeholder="roll, bottle, each"></label>
                        <label class="field-label">SKU<input class="input" name="sku" maxlength="80"></label>
                        <label class="field-label">Barcode<input class="input" name="barcode" maxlength="120"></label>
                        <label class="field-label">Manufacturer<input class="input" name="manufacturer" maxlength="120"></label>
                        <label class="field-label">Model<input class="input" name="model" maxlength="120"></label>
                        <label class="field-label">Model number<input class="input" name="model_number" maxlength="120"></label>
                        <label class="field-label">Serial number<input class="input" name="serial_number" maxlength="160"></label>
                        <label class="field-label">Supplier / vendor<input class="input" name="supplier" maxlength="160"></label>
                        <label class="field-label">Unit cost<input class="input" type="number" name="unit_cost" min="0" step="0.01"></label>
                        <label class="field-label">Low-stock threshold<input class="input" type="number" name="reorder_level" min="0" step="0.001" value="0"></label>
                        <label class="field-label">Default location<select class="input" name="default_location_id"><option value="">Not set</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                        <label class="field-label sm:col-span-2">Photo<input class="input" type="file" name="photo" accept="image/*"></label>
                        <label class="field-label sm:col-span-2">Notes<textarea class="input" name="notes" rows="2"></textarea></label>
                        <div class="sm:col-span-2"><button class="btn-primary">Add item</button></div>
                    </form>
                </section>

                <section class="card card-pad">
                    <h2 class="section-title">Stock locations</h2>
                    <p class="mt-1 text-sm text-slate-500">Create storage areas such as Supply closet, Bathroom or Kitchen.</p>
                    <form method="post" action="{{ route('admin.inventory.locations.store') }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                        <label class="field-label flex-1">Location name<input class="input" name="name" required maxlength="120" placeholder="e.g. Supply closet"></label>
                        <button class="btn-secondary">Add location</button>
                    </form>
                    <div class="mt-4 divide-y">
                        @forelse($locations as $location)
                            <div class="py-2 text-sm">{{ $location->name }}</div>
                        @empty
                            <p class="py-3 text-sm text-slate-500">No locations yet. Add a location before receiving stock.</p>
                        @endforelse
                    </div>

                    <h3 class="mt-6 font-semibold">Receive stock</h3>
                    <form method="post" action="{{ route('admin.inventory.receive') }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                        @csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                        <label class="field-label">Item<select class="input" name="item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
                        <label class="field-label">Location<select class="input" name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                        <label class="field-label">Quantity<input class="input" name="quantity" type="number" min="0.001" step="0.001" required></label>
                        <label class="field-label">Order / receipt reference<input class="input" name="reference" maxlength="160"></label>
                        <label class="field-label sm:col-span-2">Notes<input class="input" name="notes" maxlength="2000"></label>
                        <div class="sm:col-span-2"><button class="btn-primary" @disabled($items->isEmpty() || $locations->isEmpty())>Receive stock</button></div>
                    </form>
                </section>
            </div>

            <section class="card card-pad mt-5">
                <h2 class="section-title">Inventory levels</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="border-b text-left text-slate-500"><th class="py-3 pr-4">Item</th><th class="py-3 pr-4">Type</th><th class="py-3 pr-4">Location balances</th><th class="py-3 pr-4 text-right">Total</th><th class="py-3 text-right">Reorder at</th></tr></thead>
                        <tbody class="divide-y">
                            @forelse($items as $item)
                                <tr>
                                    <td class="py-3 pr-4"><div class="flex items-center gap-3">@if($item->photo_path)<img src="{{ asset('storage/'.$item->photo_path) }}" alt="" class="h-10 w-10 rounded-lg object-cover">@endif<div><div class="font-medium">{{ $item->name }}</div><div class="text-xs text-slate-500">{{ $item->sku ?: $item->barcode ?: $item->serial_number }}</div></div></div></td>
                                    <td class="py-3 pr-4">{{ ['asset' => 'Fixed asset', 'guest_supply' => 'Guest supply', 'cleaning_supply' => 'Cleaning supply', 'other' => 'Other'][$item->category] ?? 'Other' }}</td>
                                    <td class="py-3 pr-4"><div class="flex flex-wrap gap-2">@foreach($item->stocks as $balance)<span class="rounded-md bg-slate-100 px-2 py-1 text-xs">{{ $balance->location->name }}: {{ number_format((float) $balance->quantity, 3) }}</span>@endforeach</div></td>
                                    <td class="py-3 pr-4 text-right font-semibold {{ $item->is_low_stock ? 'text-amber-700' : '' }}">{{ number_format((float) $item->quantity_on_hand, 3) }} {{ $item->unit_of_measure }}</td>
                                    <td class="py-3 text-right">{{ number_format((float) $item->reorder_level, 3) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-8 text-center text-slate-500">No inventory items have been added for this property.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="mt-5 grid gap-5 xl:grid-cols-2">
                <section class="card card-pad">
                    <h2 class="section-title">Transfer stock between locations</h2>
                    <form method="post" action="{{ route('admin.inventory.transfer') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                        @csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                        <label class="field-label">Item<select class="input" name="item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
                        <label class="field-label">Quantity<input class="input" type="number" name="quantity" min="0.001" step="0.001" required></label>
                        <label class="field-label">From<select class="input" name="from_location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                        <label class="field-label">To<select class="input" name="to_location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                        <label class="field-label sm:col-span-2">Notes<input class="input" name="notes" maxlength="2000" placeholder="e.g. Replenished guest bathroom"></label>
                        <div class="sm:col-span-2"><button class="btn-secondary" @disabled($items->isEmpty() || $locations->count() < 2)>Transfer stock</button></div>
                    </form>
                </section>

                <section class="card card-pad">
                    <h2 class="section-title">Record usage</h2>
                    <p class="mt-1 text-sm text-slate-500">Use decimals for partial quantities, for example 0.5 roll or 0.25 bottle.</p>
                    <form method="post" action="{{ route('admin.inventory.consume') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                        @csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                        <label class="field-label">Item<select class="input" name="item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
                        <label class="field-label">Quantity used<input class="input" type="number" name="quantity" min="0.001" step="0.001" required></label>
                        <label class="field-label">Taken from<select class="input" name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                        <label class="field-label">Cleaning session ID (optional)<input class="input" type="number" name="cleaning_session_id" min="1"></label>
                        <label class="field-label sm:col-span-2">Notes<input class="input" name="notes" maxlength="2000" placeholder="e.g. Used to replenish guest bathroom"></label>
                        <div class="sm:col-span-2"><button class="btn-secondary" @disabled($items->isEmpty() || $locations->isEmpty())>Record usage</button></div>
                    </form>
                </section>
            </div>

            <section class="card card-pad mt-5">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <div><h2 class="section-title">Usage by stay and cleaner</h2><p class="mt-1 text-sm text-slate-500">Recorded usage linked to a cleaning session, for the last 90 days.</p></div>
                    <span class="text-xs text-slate-500">Up to 100 records</span>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="border-b text-left text-slate-500"><th class="py-3 pr-4">Date</th><th class="py-3 pr-4">Item</th><th class="py-3 pr-4">Quantity</th><th class="py-3 pr-4">Guest / stay</th><th class="py-3">Cleaner</th></tr></thead>
                        <tbody class="divide-y">
                            @forelse($usageMovements as $usage)
                                <tr>
                                    <td class="py-3 pr-4 whitespace-nowrap">{{ $usage->occurred_at?->format('M j, Y') }}</td>
                                    <td class="py-3 pr-4">{{ $usage->item->name }}</td>
                                    <td class="py-3 pr-4">{{ number_format((float) $usage->quantity, 3) }} {{ $usage->item->unit_of_measure }}</td>
                                    <td class="py-3 pr-4">{{ $usage->cleaningSession?->guestLabel() ?: 'Stay not linked' }}</td>
                                    <td class="py-3">{{ $usage->recorder?->name ?? 'Unknown' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-slate-500">No usage has been linked to a cleaning session yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card card-pad mt-5">
                <h2 class="section-title">Recent inventory activity</h2>
                <div class="mt-3 divide-y">
                    @forelse($movements as $movement)
                        <div class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                            <div><span class="font-medium">{{ $movement->item->name }}</span><span class="text-slate-500"> · {{ ucfirst($movement->movement_type) }} {{ number_format((float) $movement->quantity, 3) }}</span><div class="text-xs text-slate-500">{{ $movement->fromLocation?->name ?? 'External'}}, {{ $movement->toLocation?->name ?? '—' }} · {{ $movement->recorder?->name ?? 'System' }}</div>@if($movement->notes)<div class="text-xs text-slate-500">{{ $movement->notes }}</div>@endif</div>
                            <time class="text-xs text-slate-500">{{ $movement->occurred_at?->format('M j, Y g:i A') }}</time>
                        </div>
                    @empty
                        <p class="py-5 text-sm text-slate-500">No stock movements recorded yet.</p>
                    @endforelse
                </div>
            </section>
        @endif
    @endif
</x-admin-layout>
