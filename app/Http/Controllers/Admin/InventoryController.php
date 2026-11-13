<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\CleaningSession;
use App\Models\Property;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    private function visibleProperties(Request $request)
    {
        return Property::query()->visibleTo($request->user())->orderBy('name');
    }

    private function property(Request $request, int $propertyId): Property
    {
        return $this->visibleProperties($request)->whereKey($propertyId)->firstOrFail();
    }

    public function index(Request $request)
    {
        $properties = $this->visibleProperties($request)->get();
        $property = $properties->firstWhere('id', (int) $request->query('property_id')) ?? $properties->first();

        if (! $property) {
            return view('admin.inventory.index', ['properties' => $properties, 'property' => null, 'items' => collect(), 'locations' => collect(), 'movements' => collect()]);
        }

        $items = InventoryItem::query()
            ->where('property_id', $property->id)
            ->where('active', true)
            ->with(['defaultLocation', 'stocks.location'])
            ->withSum('stocks', 'quantity')
            ->orderBy('name')
            ->get();

        $locations = InventoryLocation::query()->where('property_id', $property->id)->orderBy('name')->get();
        $movements = InventoryMovement::query()->where('property_id', $property->id)
            ->with(['item', 'fromLocation', 'toLocation', 'recorder'])
            ->latest('occurred_at')->limit(30)->get();
        $usageMovements = InventoryMovement::query()->where('property_id', $property->id)
            ->where('movement_type', 'consume')->whereNotNull('cleaning_session_id')
            ->where('occurred_at', '>=', now()->subDays(90))
            ->with(['item', 'recorder', 'cleaningSession.booking', 'cleaningSession.checkout'])
            ->latest('occurred_at')->limit(100)->get();

        return view('admin.inventory.index', compact('properties', 'property', 'items', 'locations', 'movements', 'usageMovements'));
    }

    public function storeItem(Request $request)
    {
        $property = $this->property($request, (int) $request->input('property_id'));
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'category' => ['required', Rule::in(['asset', 'guest_supply', 'cleaning_supply', 'other'])],
            'unit_of_measure' => ['nullable', 'string', 'max:40'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'model_number' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:160'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'default_location_id' => ['nullable', 'integer', Rule::exists('inventory_locations', 'id')->where('property_id', $property->id)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('inventory', 'public');
        }
        unset($data['photo']);
        InventoryItem::create($data + ['property_id' => $property->id]);
        return back()->with('success', 'Inventory item added.');
    }

    public function updateItem(Request $request, InventoryItem $item)
    {
        $property = $this->property($request, (int) $item->property_id);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80'],
            'barcode' => ['nullable', 'string', 'max:120'],
            'category' => ['required', Rule::in(['asset', 'guest_supply', 'cleaning_supply', 'other'])],
            'unit_of_measure' => ['nullable', 'string', 'max:40'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'model_number' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:160'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'default_location_id' => ['nullable', 'integer', Rule::exists('inventory_locations', 'id')->where('property_id', $property->id)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        abort_unless((int) $item->property_id === (int) $property->id, 404);
        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('inventory', 'public');
        }
        unset($data['photo']);
        $item->update($data);
        return back()->with('success', 'Inventory item updated.');
    }

    public function storeLocation(Request $request)
    {
        $property = $this->property($request, (int) $request->input('property_id'));
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:120', Rule::unique('inventory_locations', 'name')->where('property_id', $property->id)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        InventoryLocation::create($data);
        return back()->with('success', 'Stock location created.');
    }

    public function receive(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'location_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $property = $this->property($request, (int) $data['property_id']);
        $item = InventoryItem::query()->where('property_id', $property->id)->findOrFail($data['item_id']);
        $location = InventoryLocation::query()->where('property_id', $property->id)->findOrFail($data['location_id']);
        $stock->receive($item, $location, (float) $data['quantity'], $request->user()->id, $data);
        $item->update(['last_ordered_at' => now()->toDateString()]);
        return back()->with('success', 'Stock received and added to the selected location.');
    }

    public function transfer(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'from_location_id' => ['required', 'integer'],
            'to_location_id' => ['required', 'integer', 'different:from_location_id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $property = $this->property($request, (int) $data['property_id']);
        $item = InventoryItem::query()->where('property_id', $property->id)->findOrFail($data['item_id']);
        $from = InventoryLocation::query()->where('property_id', $property->id)->findOrFail($data['from_location_id']);
        $to = InventoryLocation::query()->where('property_id', $property->id)->findOrFail($data['to_location_id']);
        $stock->transfer($item, $from, $to, (float) $data['quantity'], $request->user()->id, $data);
        return back()->with('success', 'Stock transferred.');
    }

    public function consume(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'location_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'cleaning_session_id' => ['nullable', 'integer', 'exists:cleaning_sessions,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $property = $this->property($request, (int) $data['property_id']);
        $item = InventoryItem::query()->where('property_id', $property->id)->findOrFail($data['item_id']);
        $location = InventoryLocation::query()->where('property_id', $property->id)->findOrFail($data['location_id']);
        if (! empty($data['cleaning_session_id'])) {
            $session = CleaningSession::query()->where('property_id', $property->id)->findOrFail($data['cleaning_session_id']);
            if ($request->user()->hasRole('housekeeper')) {
                abort_unless((int) $session->housekeeper_id === (int) $request->user()->id && $session->status === 'in_progress', 403);
            }
        }
        $stock->consume($item, $location, (float) $data['quantity'], $request->user()->id, $data);
        return back()->with('success', 'Usage recorded.');
    }

    public function adjust(Request $request, InventoryStockService $stock)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'owner', 'company']), 403);
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'location_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'not_in:0', 'between:-99999999,99999999'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        $property = $this->property($request, (int) $data['property_id']);
        $item = InventoryItem::query()->where('property_id', $property->id)->findOrFail($data['item_id']);
        $location = InventoryLocation::query()->where('property_id', $property->id)->findOrFail($data['location_id']);
        $stock->adjust($item, $location, (float) $data['quantity'], $request->user()->id, $data);
        return back()->with('success', 'Stock adjustment recorded.');
    }
}
