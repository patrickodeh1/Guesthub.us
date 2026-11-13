<x-admin-layout title="Property inventory">
    @php
        $section = request('section', 'overview');
        $sections = ['overview' => 'Overview', 'items' => 'Items', 'add' => 'Add item', 'stock' => 'Stock', 'orders' => 'Purchase orders', 'reports' => 'Reports'];
        $lowItems = $items->filter(fn ($item) => $item->is_low_stock);
        $totalUnits = $items->sum(fn ($item) => (float) $item->quantity_on_hand);
        $categoryNames = ['asset' => 'Fixed asset', 'guest_supply' => 'Guest supply', 'cleaning_supply' => 'Cleaning supply', 'tool' => 'Tool', 'other' => 'Other'];
    @endphp

    <div class="page-header">
        <div><p class="eyebrow">Operations</p><h1 class="page-title">Inventory</h1><p class="page-subtitle">A clear view of what each property owns, stores and uses.</p></div>
        <a href="{{ route('admin.inventory.index', ['property_id' => $property?->id, 'section' => 'add']) }}" class="btn-primary">Add item</a>
    </div>

    @if(session('success'))<div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

    @if($properties->isEmpty())
        <div class="card card-pad text-sm text-slate-500">No properties are available for your account.</div>
    @elseif($property)
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <label class="field-label w-full sm:max-w-sm">Property
                <select class="input mt-1" onchange="window.location=this.value">
                    @foreach($properties as $option)
                        <option value="{{ route('admin.inventory.index', ['property_id' => $option->id, 'section' => $section]) }}" @selected($property->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </label>
            <p class="text-xs text-slate-500">Each property's inventory is kept separate.</p>
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="card card-pad"><p class="text-xs text-slate-500">Active items</p><p class="mt-1 text-2xl font-semibold">{{ $items->count() }}</p></div>
            <div class="card card-pad"><p class="text-xs text-slate-500">Low stock</p><p class="mt-1 text-2xl font-semibold {{ $lowItems->isNotEmpty() ? 'text-amber-700' : '' }}">{{ $lowItems->count() }}</p></div>
            <div class="card card-pad"><p class="text-xs text-slate-500">Stock locations</p><p class="mt-1 text-2xl font-semibold">{{ $locations->count() }}</p></div>
            <div class="card card-pad"><p class="text-xs text-slate-500">Units on hand</p><p class="mt-1 text-2xl font-semibold">{{ number_format($totalUnits, 2) }}</p></div>
        </div>

        <nav class="mb-5 flex gap-1 overflow-x-auto rounded-xl border border-slate-200 bg-white p-1" aria-label="Inventory sections">
            @foreach($sections as $key => $label)
                <a href="{{ route('admin.inventory.index', ['property_id' => $property->id, 'section' => $key]) }}" class="shrink-0 rounded-lg px-4 py-2.5 text-sm font-medium {{ $section === $key ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
            @endforeach
        </nav>

        @if($section === 'overview')
            <section class="card card-pad mb-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="section-title">Inventory at a glance</h2><p class="mt-1 text-sm text-slate-500">Search items and quickly identify anything that needs attention.</p></div><a class="text-sm font-semibold underline" href="{{ route('admin.inventory.index', ['property_id' => $property->id, 'section' => 'items']) }}">View all items</a></div>
                <input id="inventory-search" class="input mt-4 w-full sm:max-w-md" type="search" placeholder="Search by item, SKU, barcode or supplier" autocomplete="off">
                <div class="mt-4 space-y-2" id="inventory-list">
                    @forelse($items as $item)
                        <article data-search="{{ strtolower($item->name.' '.$item->sku.' '.$item->barcode.' '.$item->supplier.' '.$item->serial_number) }}" class="inventory-row flex flex-col gap-3 rounded-xl border border-slate-200 p-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                @if($item->photo_path)<img src="{{ asset('storage/'.$item->photo_path) }}" alt="" class="h-12 w-12 shrink-0 rounded-lg border border-slate-100 object-cover">@else<div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500"><span class="text-xs font-semibold">{{ strtoupper(substr($item->name, 0, 2)) }}</span></div>@endif
                                <div class="min-w-0"><p class="truncate font-semibold text-slate-900">{{ $item->name }}</p><p class="text-xs text-slate-500">{{ $categoryNames[$item->category] ?? 'Other' }}@if($item->unit_of_measure) · {{ $item->unit_of_measure }}@endif</p><p class="truncate text-xs text-slate-400">{{ $item->sku ?: ($item->barcode ?: 'No item code') }}</p></div>
                            </div>
                            <div class="flex items-center justify-between gap-3 sm:justify-end"><div class="text-left sm:text-right"><p class="font-semibold tabular-nums">{{ number_format((float) $item->quantity_on_hand, 2) }} {{ $item->unit_of_measure }}</p><p class="text-xs {{ $item->is_low_stock ? 'font-semibold text-amber-700' : 'text-slate-500' }}">{{ $item->is_low_stock ? 'Low stock · reorder at '.number_format((float) $item->reorder_level, 2) : 'In stock' }}</p></div><a class="btn-secondary !px-3 !py-2 text-xs" href="{{ route('admin.inventory.index', ['property_id' => $property->id, 'section' => 'items']) }}#item-{{ $item->id }}">Details</a></div>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center"><p class="font-medium">No inventory items yet</p><p class="mt-1 text-sm text-slate-500">Start by adding the supplies and assets used at this property.</p><a class="btn-primary mt-4 inline-flex" href="{{ route('admin.inventory.index', ['property_id' => $property->id, 'section' => 'items', 'create' => 1]) }}">Add your first item</a></div>
                    @endforelse
                </div>
                <p id="inventory-no-results" class="hidden py-5 text-center text-sm text-slate-500">No items match your search.</p>
            </section>
            @if($lowItems->isNotEmpty())
                <section class="card card-pad"><h2 class="section-title">Needs reordering</h2><div class="mt-3 divide-y divide-slate-100">@foreach($lowItems as $item)<div class="flex flex-wrap items-center justify-between gap-2 py-3"><div><p class="font-medium">{{ $item->name }}</p><p class="text-xs text-slate-500">{{ number_format((float) $item->quantity_on_hand, 2) }} {{ $item->unit_of_measure }} remaining · threshold {{ number_format((float) $item->reorder_level, 2) }}</p></div><a class="text-sm font-semibold underline" href="{{ route('admin.inventory.index', ['property_id' => $property->id, 'section' => 'orders', 'item_id' => $item->id]) }}">Create order</a></div>@endforeach</div></section>
            @endif
        @elseif($section === 'add')
            <section class="card card-pad mb-5" id="add-item">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="section-title">Add an inventory item</h2><p class="mt-1 text-sm text-slate-500">Start with the basics. Add asset or purchasing details only when they apply.</p></div><span class="text-xs text-slate-500">Property: {{ $property->name }}</span></div>
                <form method="post" enctype="multipart/form-data" action="{{ route('admin.inventory.items.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                    <label class="field-label sm:col-span-2">Item name<input class="input mt-1" name="name" required maxlength="160" placeholder="e.g. Bath towels"></label>
                    <label class="field-label">Category<select class="input mt-1" name="category" required><option value="guest_supply">Guest supply</option><option value="cleaning_supply">Cleaning supply</option><option value="asset">Fixed asset</option><option value="tool">Tool</option><option value="other">Other</option></select><span class="mt-1 block text-xs font-normal text-slate-500">Choose what best describes the item. Fixed assets are tracked individually; supplies are counted by quantity.</span></label>
                    <label class="field-label">Unit of measure<input class="input mt-1" name="unit_of_measure" maxlength="40" placeholder="each, bottle, roll, pack"><span class="mt-1 block text-xs font-normal text-slate-500">Use the unit your team counts. Fractions are supported.</span></label>
                    <label class="field-label">Photo<input class="input mt-1" type="file" name="photo" accept="image/*"></label>
                    <label class="field-label">Default stock location<select class="input mt-1" name="default_location_id"><option value="">Choose later</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                    <details class="rounded-xl border border-slate-200 p-4 sm:col-span-2"><summary class="cursor-pointer font-semibold text-sm">Identification details <span class="font-normal text-slate-500">· SKU, barcode and asset serial number</span></summary><div class="mt-4 grid gap-3 sm:grid-cols-2"><label class="field-label">SKU<input class="input mt-1" name="sku" maxlength="80" placeholder="Your internal item code"></label><label class="field-label">Barcode value<input class="input mt-1" name="barcode" maxlength="120" placeholder="Scan or enter the printed barcode"><span class="mt-1 block text-xs font-normal text-slate-500">A barcode is stored as text, not a numeric quantity. A scanner can enter its encoded value here.</span></label><label class="field-label">Manufacturer<input class="input mt-1" name="manufacturer" maxlength="120"></label><label class="field-label">Model<input class="input mt-1" name="model" maxlength="120"></label><label class="field-label">Model number<input class="input mt-1" name="model_number" maxlength="120"></label><label class="field-label">Serial number<input class="input mt-1" name="serial_number" maxlength="160"><span class="mt-1 block text-xs font-normal text-slate-500">Usually unique to one physical asset.</span></label></div></details>
                    <details class="rounded-xl border border-slate-200 p-4 sm:col-span-2"><summary class="cursor-pointer font-semibold text-sm">Purchasing and reorder details <span class="font-normal text-slate-500">· optional</span></summary><div class="mt-4 grid gap-3 sm:grid-cols-2"><label class="field-label">Supplier / vendor<input class="input mt-1" name="supplier" maxlength="160"></label><label class="field-label">Unit cost<input class="input mt-1" type="number" name="unit_cost" min="0" step="0.01"></label><label class="field-label">Low-stock threshold<input class="input mt-1" type="number" name="reorder_level" min="0" step="0.001" value="0"><span class="mt-1 block text-xs font-normal text-slate-500">We'll flag the item when stock reaches this amount.</span></label></div></details>
                    <label class="field-label sm:col-span-2">Notes<textarea class="input mt-1" name="notes" rows="2" placeholder="Optional description or handling notes"></textarea></label>
                    <div class="sm:col-span-2 flex flex-wrap gap-2"><button class="btn-primary">Save item</button><a href="{{ route('admin.inventory.index', ['property_id' => $property->id, 'section' => 'items']) }}" class="btn-secondary">Cancel</a></div>
                </form>
            </section>

        @elseif($section === 'items')
            <section class="card card-pad"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="section-title">Items at this property</h2><p class="mt-1 text-sm text-slate-500">{{ $items->count() }} active items</p></div><input id="item-search" class="input w-full sm:max-w-xs" type="search" placeholder="Search inventory"></div>
                <div class="mt-4 space-y-3" id="item-cards">
                    @forelse($items as $item)
                        <article id="item-{{ $item->id }}" data-item-search="{{ strtolower($item->name.' '.$item->sku.' '.$item->barcode.' '.$item->supplier.' '.$item->serial_number) }}" class="rounded-xl border border-slate-200 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="flex min-w-0 items-center gap-3">@if($item->photo_path)<img src="{{ asset('storage/'.$item->photo_path) }}" alt="" class="h-12 w-12 rounded-lg object-cover">@else<div class="h-12 w-12 rounded-lg bg-slate-100"></div>@endif<div class="min-w-0"><p class="font-semibold">{{ $item->name }}</p><p class="text-xs text-slate-500">{{ $categoryNames[$item->category] ?? 'Other' }} · {{ $item->sku ?: 'No SKU' }}</p></div></div>
                                <div class="sm:text-right"><p class="text-lg font-semibold">{{ number_format((float) $item->quantity_on_hand, 2) }} {{ $item->unit_of_measure }}</p><span class="text-xs {{ $item->is_low_stock ? 'font-semibold text-amber-700' : 'text-slate-500' }}">{{ $item->is_low_stock ? 'Low stock' : 'Stock on hand' }}</span></div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">@forelse($item->stocks as $balance)<span class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">{{ $balance->location->name }}: <strong>{{ number_format((float) $balance->quantity, 2) }}</strong></span>@empty<span class="text-xs text-slate-400">No stock recorded yet</span>@endforelse</div>
                            <details class="mt-4 border-t border-slate-100 pt-3"><summary class="cursor-pointer text-sm font-semibold text-slate-700">Edit item details</summary><form method="post" enctype="multipart/form-data" action="{{ route('admin.inventory.items.update', $item) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf @method('put')
                                <label class="field-label">Name<input class="input mt-1" name="name" value="{{ $item->name }}" required></label>
                                <label class="field-label">Category<select class="input mt-1" name="category">@foreach($categoryNames as $key => $label)<option value="{{ $key }}" @selected($item->category === $key)>{{ $label }}</option>@endforeach</select></label>
                                <label class="field-label">Unit<input class="input mt-1" name="unit_of_measure" value="{{ $item->unit_of_measure }}"></label>
                                <label class="field-label">SKU<input class="input mt-1" name="sku" value="{{ $item->sku }}"></label>
                                <label class="field-label">Barcode value<input class="input mt-1" name="barcode" value="{{ $item->barcode }}"></label>
                                <label class="field-label">Supplier<input class="input mt-1" name="supplier" value="{{ $item->supplier }}"></label>
                                <label class="field-label">Manufacturer<input class="input mt-1" name="manufacturer" value="{{ $item->manufacturer }}"></label>
                                <label class="field-label">Model<input class="input mt-1" name="model" value="{{ $item->model }}"></label>
                                <label class="field-label">Model number<input class="input mt-1" name="model_number" value="{{ $item->model_number }}"></label>
                                <label class="field-label">Serial number<input class="input mt-1" name="serial_number" value="{{ $item->serial_number }}"></label>
                                <label class="field-label">Supplier unit cost<input class="input mt-1" type="number" min="0" step="0.01" name="unit_cost" value="{{ $item->unit_cost }}"></label>
                                <label class="field-label">Reorder threshold<input class="input mt-1" type="number" min="0" step="0.001" name="reorder_level" value="{{ $item->reorder_level }}"></label>
                                <label class="field-label sm:col-span-2">Default location<select class="input mt-1" name="default_location_id"><option value="">Not set</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected($item->default_location_id === $location->id)>{{ $location->name }}</option>@endforeach</select></label>
                                <label class="field-label sm:col-span-2">Replace photo<input class="input mt-1" type="file" name="photo" accept="image/*"></label>
                                <label class="field-label sm:col-span-2">Notes<textarea class="input mt-1" name="notes" rows="2">{{ $item->notes }}</textarea></label>
                                <div class="sm:col-span-2"><button class="btn-secondary">Save changes</button></div>
                            </form></details>
                        </article>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">No items added yet. Use “Add an inventory item” to get started.</p>
                    @endforelse
                </div>
            </section>
        @elseif($section === 'stock')
            <div class="grid gap-5 xl:grid-cols-2">
                <section class="card card-pad"><h2 class="section-title">Stock locations</h2><p class="mt-1 text-sm text-slate-500">Locations are physical places where stock is stored, such as a supply closet, storeroom or guest bathroom.</p>
                    <form method="post" action="{{ route('admin.inventory.locations.store') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">@csrf<input type="hidden" name="property_id" value="{{ $property->id }}"><label class="field-label">New location<input class="input mt-1" name="name" required maxlength="120" placeholder="e.g. Main supply closet"></label><button class="btn-secondary self-end">Add location</button></form>
                    <div class="mt-4 divide-y divide-slate-100">@forelse($locations as $location)<div class="py-3"><p class="font-medium text-sm">{{ $location->name }}</p><p class="text-xs text-slate-500">{{ $location->notes ?: 'Stock stored at this location' }}</p></div>@empty<p class="py-3 text-sm text-slate-500">Add your first location before receiving stock.</p>@endforelse</div>
                </section>
                <section class="card card-pad"><h2 class="section-title">Receive stock</h2><p class="mt-1 text-sm text-slate-500">Use this when a delivery arrives. This increases the quantity at the selected location.</p>
                    <form method="post" action="{{ route('admin.inventory.receive') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                        <label class="field-label sm:col-span-2">Item<select class="input mt-1" name="item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
                        <label class="field-label">Put away at<select class="input mt-1" name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                        <label class="field-label">Quantity received<input class="input mt-1" name="quantity" type="number" min="0.001" step="0.001" required placeholder="e.g. 12"></label>
                        <label class="field-label">Supplier<input class="input mt-1" name="supplier" maxlength="160"></label><label class="field-label">Purchase date<input class="input mt-1" type="date" name="ordered_at"></label>
                        <label class="field-label sm:col-span-2">Receipt / order reference<input class="input mt-1" name="reference" maxlength="160"></label><label class="field-label sm:col-span-2">Notes<input class="input mt-1" name="notes" maxlength="2000"></label>
                        <div class="sm:col-span-2"><button class="btn-primary" @disabled($items->isEmpty() || $locations->isEmpty())>Add received stock</button></div>
                    </form>
                </section>
                <section class="card card-pad"><h2 class="section-title">Move stock</h2><p class="mt-1 text-sm text-slate-500">A transfer moves stock between locations. It does not change the property's total quantity.</p>
                    <form method="post" action="{{ route('admin.inventory.transfer') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                        <label class="field-label sm:col-span-2">Item<select class="input mt-1" name="item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
                        <label class="field-label">From<select class="input mt-1" name="from_location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                        <label class="field-label">To<select class="input mt-1" name="to_location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                        <label class="field-label">Quantity to move<input class="input mt-1" type="number" name="quantity" min="0.001" step="0.001" required></label><label class="field-label">Reason / notes<input class="input mt-1" name="notes" maxlength="2000"></label>
                        <div class="sm:col-span-2"><button class="btn-primary" @disabled($items->isEmpty() || $locations->count() < 2)>Transfer stock</button></div>
                    </form>
                </section>
                <section class="card card-pad"><h2 class="section-title">Record usage</h2><p class="mt-1 text-sm text-slate-500">Use this when supplies are consumed during cleaning or maintenance. The quantity is deducted from that location.</p>
                    <form method="post" action="{{ route('admin.inventory.consume') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                        <label class="field-label sm:col-span-2">Item<select class="input mt-1" name="item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>
                        <label class="field-label">Used from<select class="input mt-1" name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label><label class="field-label">Quantity used<input class="input mt-1" type="number" name="quantity" min="0.001" step="0.001" required></label>
                        <label class="field-label sm:col-span-2">Cleaning session ID (optional)<input class="input mt-1" type="number" name="cleaning_session_id" min="1"></label><label class="field-label sm:col-span-2">Notes<input class="input mt-1" name="notes" maxlength="2000"></label>
                        <div class="sm:col-span-2"><button class="btn-primary" @disabled($items->isEmpty() || $locations->isEmpty())>Record usage</button></div>
                    </form>
                </section>
                <section class="card card-pad xl:col-span-2"><h2 class="section-title">Record damaged, wasted or missing stock</h2><p class="mt-1 text-sm text-slate-500">Use waste for damaged/expired supplies. Use missing or suspected theft when stock cannot be accounted for.</p>
                    <form method="post" action="{{ route('admin.inventory.loss') }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                        <label class="field-label">Item<select class="input mt-1" name="item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label><label class="field-label">Location<select class="input mt-1" name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                        <label class="field-label">What happened?<select class="input mt-1" name="movement_type" required><option value="waste">Damaged / expired / wasted</option><option value="theft">Missing / suspected theft</option></select></label><label class="field-label">Quantity<input class="input mt-1" type="number" name="quantity" min="0.001" step="0.001" required></label><label class="field-label sm:col-span-2">Explain what happened<input class="input mt-1" name="notes" maxlength="2000" required></label><div class="sm:col-span-2"><button class="btn-secondary" @disabled($items->isEmpty() || $locations->isEmpty())>Save stock loss</button></div>
                    </form>
                </section>
            </div>
        @elseif($section === 'orders')
            <section class="card card-pad mb-5"><h2 class="section-title">Create a purchase order</h2><p class="mt-1 text-sm text-slate-500">Record an order before delivery. You can receive it in full or in separate deliveries later.</p>
                <form method="post" action="{{ route('admin.inventory.purchase-orders.store') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@csrf<input type="hidden" name="property_id" value="{{ $property->id }}">
                    <label class="field-label">Item<select id="po-item-id" class="input mt-1" name="item_id" required>@foreach($items as $item)<option value="{{ $item->id }}" @selected(request('item_id') == $item->id)>{{ $item->name }}</option>@endforeach</select></label><label class="field-label">Destination<select class="input mt-1" name="destination_location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                    <label class="field-label">Supplier / vendor<input id="po-supplier" class="input mt-1" name="supplier" maxlength="160" required></label><label class="field-label">Order reference<input class="input mt-1" name="reference" maxlength="160"></label><label class="field-label">Order date<input class="input mt-1" type="date" name="ordered_at"></label><label class="field-label">Quantity ordered<input id="po-quantity" class="input mt-1" type="number" name="quantity_ordered" min="0.001" step="0.001" required></label><label class="field-label">Unit cost<input id="po-unit-cost" class="input mt-1" type="number" name="unit_cost" min="0" step="0.01"></label><label class="field-label sm:col-span-2 lg:col-span-3">Notes<input class="input mt-1" name="notes" maxlength="2000"></label><div class="sm:col-span-2 lg:col-span-3"><button class="btn-primary" @disabled($items->isEmpty() || $locations->isEmpty())>Create purchase order</button></div>
                </form>
            </section>
            <section class="card card-pad"><h2 class="section-title">Purchase order history</h2><div class="mt-4 space-y-3">
                @forelse($purchaseOrders as $order)
                    <article class="rounded-xl border border-slate-200 p-4"><div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><p class="font-semibold">{{ $order->reference }} · {{ $order->supplier }}</p><p class="text-xs text-slate-500">Ordered {{ $order->ordered_at?->format('M j, Y') ?? 'date not set' }} · {{ ucfirst(str_replace('_', ' ', $order->status)) }}</p></div><span class="text-xs text-slate-500">{{ $order->creator?->name ?? 'Team member' }}</span></div>
                    @foreach($order->lines as $line)@php($outstanding = max(0, round((float) $line->quantity_ordered - (float) $line->quantity_received, 3)))
                        <div class="mt-3 border-t border-slate-100 pt-3"><div class="flex flex-wrap justify-between gap-2 text-sm"><span class="font-medium">{{ $line->item?->name ?? 'Item removed' }} → {{ $line->destinationLocation?->name ?? 'Location removed' }}</span><span>{{ number_format((float) $line->quantity_received, 2) }} / {{ number_format((float) $line->quantity_ordered, 2) }} received</span></div>
                        @if($outstanding > 0 && $line->item && $line->destinationLocation)<form method="post" action="{{ route('admin.inventory.purchase-orders.receive', $order) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf<input type="hidden" name="line_id" value="{{ $line->id }}"><label class="field-label w-full sm:w-40">Receive this delivery<input class="input mt-1" type="number" name="quantity" min="0.001" max="{{ $outstanding }}" step="0.001" value="{{ $outstanding }}" required></label><button class="btn-secondary">Confirm received stock</button><span class="text-xs text-slate-500">{{ number_format($outstanding, 2) }} still outstanding</span></form>@endif
                        </div>
                    @endforeach</article>
                @empty<p class="py-8 text-center text-sm text-slate-500">No purchase orders yet. Create one above when you need to reorder.</p>@endforelse
            </div></section>
        @elseif($section === 'reports')
            <section class="card card-pad mb-5"><h2 class="section-title">Guest supply usage</h2><p class="mt-1 text-sm text-slate-500">Estimated guest consumption based on cleaner counts recorded over the last 90 days.</p><div class="mt-4 space-y-3">
                @forelse($sessionRecords as $record)<article class="rounded-xl border border-slate-200 p-3"><div class="flex flex-col gap-1 sm:flex-row sm:justify-between"><p class="font-semibold">{{ $record->item->name }}</p><span class="text-xs text-slate-500">{{ $record->created_at?->format('M j, Y') }}</span></div><p class="mt-1 text-sm text-slate-600">Guest use: {{ $record->quantity_guest_consumed === null ? 'Not yet available' : number_format((float) $record->quantity_guest_consumed, 2).' '.$record->item->unit_of_measure }} · Replenished: {{ number_format((float) $record->quantity_taken, 2) }} {{ $record->item->unit_of_measure }}</p><p class="text-xs text-slate-500">{{ $record->cleaningSession?->guestLabel() ?: 'Stay not linked' }} · Cleaner: {{ $record->recorder?->name ?? 'Unknown' }}</p></article>@empty<p class="py-8 text-center text-sm text-slate-500">No cleaner inventory records have been saved yet.</p>@endforelse
            </div></section>
            <section class="card card-pad"><h2 class="section-title">Stock movement history</h2><p class="mt-1 text-sm text-slate-500">Latest 30 receipts, transfers, usage and adjustments.</p><div class="mt-4 divide-y divide-slate-100">
                @forelse($movements as $movement)<article class="py-3"><div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><p class="font-medium">{{ $movement->item->name }} <span class="font-normal text-slate-500">· {{ ucfirst(str_replace('_', ' ', $movement->movement_type)) }}</span></p><span class="text-xs text-slate-500">{{ $movement->occurred_at?->format('M j, Y g:i A') }}</span></div><p class="text-sm text-slate-600">{{ number_format((float) $movement->quantity, 2) }} {{ $movement->item->unit_of_measure }} · {{ $movement->fromLocation?->name ?? 'External' }} → {{ $movement->toLocation?->name ?? '—' }}</p><p class="text-xs text-slate-500">{{ $movement->recorder?->name ?? 'System' }}@if($movement->supplier) · {{ $movement->supplier }}@endif @if($movement->reference) · {{ $movement->reference }}@endif</p>@if($movement->notes)<p class="mt-1 text-xs text-slate-500">{{ $movement->notes }}</p>@endif</article>@empty<p class="py-8 text-center text-sm text-slate-500">No stock movements have been recorded.</p>@endforelse
            </div></section>
        @endif
    @endif

    <script>
        (() => {
            const overviewSearch = document.getElementById('inventory-search');
            const overviewRows = [...document.querySelectorAll('.inventory-row')];
            const overviewEmpty = document.getElementById('inventory-no-results');
            if (overviewSearch) overviewSearch.addEventListener('input', () => {
                const term = overviewSearch.value.trim().toLowerCase();
                let shown = 0;
                overviewRows.forEach(row => { const match = row.dataset.search.includes(term); row.classList.toggle('hidden', !match); if (match) shown++; });
                if (overviewEmpty) overviewEmpty.classList.toggle('hidden', shown > 0);
            });
            const itemSearch = document.getElementById('item-search');
            const itemCards = [...document.querySelectorAll('[data-item-search]')];
            if (itemSearch) itemSearch.addEventListener('input', () => {
                const term = itemSearch.value.trim().toLowerCase();
                itemCards.forEach(card => card.classList.toggle('hidden', !card.dataset.itemSearch.includes(term)));
            });
            
        })();
    </script>
</x-admin-layout>