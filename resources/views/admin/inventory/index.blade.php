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
                        <label class="field-label">Supplier / vendor<input class="input" name="supplier" maxlength="160" placeholder="e.g. Amazon, Walmart"></label>
                        <label class="field-label">Purchase date<input class="input" type="date" name="ordered_at"></label>
                        <label class="field-label sm:col-span-2">Notes<input class="input" name="notes" maxlength="2000"></label>
                        <div class="sm:col-span-2"><button class="btn-primary" @disabled($items->isEmpty() || $locations->isEmpty())>Receive stock</button></div>
                    </form>
                </section>
            </div>

            <section id="create-purchase-order" class="card card-pad mt-5">
                <h2 class="section-title">Create purchase order</h2>
                <p class="mt-1 text-sm text-slate-500">Record what you ordered. When a delivery arrives, receive all or part of it into the selected stock location.</p>
                <form method="post" action="{{ route('admin.inventory.purchase-orders.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                    <label class="field-label">Item<select id="po-item-id" class="input" name="item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
                    <label class="field-label">Receive into<select class="input" name="destination_location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                    <label class="field-label">Supplier / vendor<input id="po-supplier" class="input" name="supplier" maxlength="160" required placeholder="e.g. Amazon, Walmart"></label>
                    <label class="field-label">Order reference<input class="input" name="reference" maxlength="160" placeholder="Optional"></label>
                    <label class="field-label">Order date<input class="input" type="date" name="ordered_at"></label>
                    <label class="field-label">Quantity ordered<input id="po-quantity" class="input" type="number" name="quantity_ordered" min="0.001" step="0.001" required></label>
                    <label class="field-label">Unit cost<input id="po-unit-cost" class="input" type="number" name="unit_cost" min="0" step="0.01"></label>
                    <label class="field-label sm:col-span-2">Notes<input class="input" name="notes" maxlength="2000"></label>
                    <div class="sm:col-span-2 lg:col-span-3"><button class="btn-primary" @disabled($items->isEmpty() || $locations->isEmpty())>Create purchase order</button></div>
                </form>

                <div class="mt-6 border-t border-slate-200 pt-5">
                    <h3 class="font-semibold text-slate-900">Recent purchase orders</h3>
                    <div class="mt-3 space-y-3">
                        @forelse($purchaseOrders as $order)
                            <div class="rounded-xl border border-slate-200 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $order->reference }} · {{ $order->supplier }}</p>
                                        <p class="mt-1 text-xs text-slate-500">Ordered {{ $order->ordered_at?->format('M j, Y') ?? 'date not set' }} · {{ ucfirst(str_replace('_', ' ', $order->status)) }}</p>
                                    </div>
                                    <span class="text-xs text-slate-500">{{ $order->creator?->name ?? 'Team member' }}</span>
                                </div>
                                @foreach($order->lines as $line)
                                    @php $outstanding = max(0, round((float) $line->quantity_ordered - (float) $line->quantity_received, 3)) @endphp
                                    <div class="mt-3 border-t border-slate-100 pt-3">
                                        <div class="flex flex-wrap justify-between gap-2 text-sm">
                                            <span class="font-medium">{{ $line->item?->name ?? 'Item removed' }} → {{ $line->destinationLocation?->name ?? 'Location removed' }}</span>
                                            <span class="text-slate-600">{{ number_format((float) $line->quantity_received, 3) }} / {{ number_format((float) $line->quantity_ordered, 3) }} received</span>
                                        </div>
                                        @if($outstanding > 0 && $line->item && $line->destinationLocation)
                                            <form method="post" action="{{ route('admin.inventory.purchase-orders.receive', $order) }}" class="mt-3 flex flex-wrap items-end gap-2">
                                                @csrf<input type="hidden" name="line_id" value="{{ $line->id }}">
                                                <label class="field-label w-36">Receive now<input class="input" type="number" name="quantity" min="0.001" max="{{ $outstanding }}" step="0.001" value="{{ $outstanding }}" required></label>
                                                <button class="btn-secondary">Add received stock</button>
                                                <span class="text-xs text-slate-500">{{ number_format($outstanding, 3) }} outstanding</span>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @empty
                            <p class="py-3 text-sm text-slate-500">No purchase orders yet. Create one when you need to reorder supplies or assets.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="card card-pad mt-5">
                <h2 class="section-title">Inventory levels</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="border-b text-left text-slate-500"><th class="py-3 pr-4">Item</th><th class="py-3 pr-4">Type</th><th class="py-3 pr-4">Location balances</th><th class="py-3 pr-4 text-right">Total</th><th class="py-3 text-right">Reorder at</th></tr></thead>
                        <tbody class="divide-y">
                            @forelse($items as $item)
                                <tr>
                                    <td class="py-3 pr-4">
                                        <div class="flex items-center gap-3">@if($item->photo_path)<img src="{{ asset('storage/'.$item->photo_path) }}" alt="" class="h-10 w-10 rounded-lg object-cover">@endif<div><div class="font-medium">{{ $item->name }}</div><div class="text-xs text-slate-500">{{ $item->sku ?: $item->barcode ?: $item->serial_number }}</div><div class="text-xs text-slate-400">{{ $item->supplier }}{{ $item->last_ordered_at ? ' · Ordered '.$item->last_ordered_at->format('M j, Y') : '' }}</div></div></div>
                                        <details class="mt-2">
                                            <summary class="cursor-pointer text-xs font-medium text-slate-600">Edit item details</summary>
                                            <form method="post" enctype="multipart/form-data" action="{{ route('admin.inventory.items.update', $item) }}" class="mt-3 grid min-w-[280px] gap-2 sm:grid-cols-2">
                                                @csrf @method('put')
                                                <label class="field-label">Name<input class="input" name="name" value="{{ $item->name }}" required></label>
                                                <label class="field-label">Category<select class="input" name="category">@foreach(['guest_supply' => 'Guest supply', 'cleaning_supply' => 'Cleaning supply', 'asset' => 'Fixed asset', 'other' => 'Other'] as $key => $label)<option value="{{ $key }}" @selected($item->category === $key)>{{ $label }}</option>@endforeach</select></label>
                                                <label class="field-label">Unit<input class="input" name="unit_of_measure" value="{{ $item->unit_of_measure }}"></label>
                                                <label class="field-label">SKU<input class="input" name="sku" value="{{ $item->sku }}"></label>
                                                <label class="field-label">Barcode<input class="input" name="barcode" value="{{ $item->barcode }}"></label>
                                                <label class="field-label">Supplier<input class="input" name="supplier" value="{{ $item->supplier }}"></label>
                                                <label class="field-label">Manufacturer<input class="input" name="manufacturer" value="{{ $item->manufacturer }}"></label>
                                                <label class="field-label">Model<input class="input" name="model" value="{{ $item->model }}"></label>
                                                <label class="field-label">Model number<input class="input" name="model_number" value="{{ $item->model_number }}"></label>
                                                <label class="field-label">Serial number<input class="input" name="serial_number" value="{{ $item->serial_number }}"></label>
                                                <label class="field-label">Unit cost<input class="input" type="number" min="0" step="0.01" name="unit_cost" value="{{ $item->unit_cost }}"></label>
                                                <label class="field-label">Reorder level<input class="input" type="number" min="0" step="0.001" name="reorder_level" value="{{ $item->reorder_level }}"></label>
                                                <label class="field-label sm:col-span-2">Default location<select class="input" name="default_location_id"><option value="">Not set</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected($item->default_location_id === $location->id)>{{ $location->name }}</option>@endforeach</select></label>
                                                <label class="field-label sm:col-span-2">Replace photo<input class="input" type="file" name="photo" accept="image/*"></label>
                                                <label class="field-label sm:col-span-2">Notes<textarea class="input" name="notes" rows="2">{{ $item->notes }}</textarea></label>
                                                <div class="sm:col-span-2"><button class="btn-secondary">Save item</button></div>
                                            </form>
                                        </details>
                                    </td>
                                    <td class="py-3 pr-4">{{ ['asset' => 'Fixed asset', 'guest_supply' => 'Guest supply', 'cleaning_supply' => 'Cleaning supply', 'other' => 'Other'][$item->category] ?? 'Other' }}</td>
                                    <td class="py-3 pr-4"><div class="flex flex-wrap gap-2">@foreach($item->stocks as $balance)<span class="rounded-md bg-slate-100 px-2 py-1 text-xs">{{ $balance->location->name }}: {{ number_format((float) $balance->quantity, 3) }}</span>@endforeach</div></td>
                                    <td class="py-3 pr-4 text-right font-semibold {{ $item->is_low_stock ? 'text-amber-700' : '' }}">{{ number_format((float) $item->quantity_on_hand, 3) }} {{ $item->unit_of_measure }}</td>
                                    <td class="py-3 text-right">{{ number_format((float) $item->reorder_level, 3) }}@if($item->is_low_stock)<button type="button" data-reorder-item="{{ $item->id }}" data-reorder-supplier="{{ $item->supplier }}" data-reorder-cost="{{ $item->unit_cost }}" data-reorder-quantity="{{ max(1, round((float) $item->reorder_level - (float) $item->quantity_on_hand, 3)) }}" class="mt-2 block text-xs font-semibold text-emerald-700 underline">Reorder</button>@endif</td>
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
                    <div><h2 class="section-title">Guest supply usage by stay and cleaner</h2><p class="mt-1 text-sm text-slate-500">Based on before-restock counts recorded during cleaning sessions in the last 90 days.</p></div>
                    <span class="text-xs text-slate-500">Up to 100 records</span>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="border-b text-left text-slate-500"><th class="py-3 pr-4">Date</th><th class="py-3 pr-4">Item</th><th class="py-3 pr-4">Guest use</th><th class="py-3 pr-4">Replenished</th><th class="py-3 pr-4">Guest / stay</th><th class="py-3">Cleaner</th></tr></thead>
                        <tbody class="divide-y">
                            @forelse($sessionRecords as $record)
                                <tr>
                                    <td class="py-3 pr-4 whitespace-nowrap">{{ $record->created_at?->format('M j, Y') }}</td>
                                    <td class="py-3 pr-4">{{ $record->item->name }}</td>
                                    <td class="py-3 pr-4">{{ $record->quantity_guest_consumed === null ? '—' : number_format((float) $record->quantity_guest_consumed, 3).' '.$record->item->unit_of_measure }}</td>
                                    <td class="py-3 pr-4">{{ number_format((float) $record->quantity_taken, 3) }} {{ $record->item->unit_of_measure }}</td>
                                    <td class="py-3 pr-4">{{ $record->cleaningSession?->guestLabel() ?: 'Stay not linked' }}</td>
                                    <td class="py-3">{{ $record->recorder?->name ?? 'Unknown' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-6 text-center text-slate-500">No cleaner inventory records have been saved yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card card-pad mt-5">
                <h2 class="section-title">Record waste or theft</h2>
                <p class="mt-1 text-sm text-slate-500">This removes the quantity from the selected location and keeps a reason in the movement history.</p>
                <form method="post" action="{{ route('admin.inventory.loss') }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                    @csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                    <label class="field-label">Item<select class="input" name="item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
                    <label class="field-label">Location<select class="input" name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                    <label class="field-label">Type<select class="input" name="movement_type" required><option value="waste">Waste / damaged</option><option value="theft">Missing / suspected theft</option></select></label>
                    <label class="field-label">Quantity<input class="input" type="number" name="quantity" min="0.001" step="0.001" required></label>
                    <label class="field-label sm:col-span-2">Reason / notes<input class="input" name="notes" maxlength="2000" required></label>
                    <div class="sm:col-span-2"><button class="btn-secondary" @disabled($items->isEmpty() || $locations->isEmpty())>Record loss</button></div>
                </form>
            </section>

            <section class="card card-pad mt-5">
                <h2 class="section-title">Recent inventory activity</h2>
                <div class="mt-3 divide-y">
                    @forelse($movements as $movement)
                        <div class="flex flex-wrap items-center justify-between gap-2 py-3 text-sm">
                            <div><span class="font-medium">{{ $movement->item->name }}</span><span class="text-slate-500"> · {{ ucfirst($movement->movement_type) }} {{ number_format((float) $movement->quantity, 3) }}</span><div class="text-xs text-slate-500">{{ $movement->fromLocation?->name ?? 'External'}}, {{ $movement->toLocation?->name ?? '—' }} · {{ $movement->recorder?->name ?? 'System' }}</div>@if($movement->supplier || $movement->reference || $movement->ordered_at)<div class="text-xs text-slate-500">{{ $movement->supplier }} {{ $movement->reference ? '· '.$movement->reference : '' }} {{ $movement->ordered_at ? '· Ordered '.$movement->ordered_at->format('M j, Y') : '' }}</div>@endif@if($movement->notes)<div class="text-xs text-slate-500">{{ $movement->notes }}</div>@endif</div>
                            <time class="text-xs text-slate-500">{{ $movement->occurred_at?->format('M j, Y g:i A') }}</time>
                        </div>
                    @empty
                        <p class="py-5 text-sm text-slate-500">No stock movements recorded yet.</p>
                    @endforelse
                </div>
            </section>
        @endif
        <script>
            document.querySelectorAll('[data-reorder-item]').forEach((button) => {
                button.addEventListener('click', () => {
                    const itemSelect = document.getElementById('po-item-id');
                    if (itemSelect) itemSelect.value = button.dataset.reorderItem;
                    const supplier = document.getElementById('po-supplier');
                    const unitCost = document.getElementById('po-unit-cost');
                    const quantity = document.getElementById('po-quantity');
                    if (supplier) supplier.value = button.dataset.reorderSupplier || '';
                    if (unitCost) unitCost.value = button.dataset.reorderCost || '';
                    if (quantity && Number(button.dataset.reorderQuantity) > 0) quantity.value = button.dataset.reorderQuantity;
                    document.getElementById('create-purchase-order')?.scrollIntoView({behavior: 'smooth', block: 'start'});
                });
            });
        </script>
    @endif
</x-admin-layout>
