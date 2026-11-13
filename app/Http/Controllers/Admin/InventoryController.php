<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryPurchaseOrder;
use App\Models\CleaningSession;
use App\Models\InventorySessionRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Property;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            return view('admin.inventory.index', ['properties' => $properties, 'property' => null, 'items' => collect(), 'locations' => collect(), 'movements' => collect(), 'purchaseOrders' => collect()]);
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
        $purchaseOrders = InventoryPurchaseOrder::query()->where('property_id', $property->id)
            ->with(['lines.item', 'lines.destinationLocation', 'creator'])
            ->latest('ordered_at')->latest('id')->limit(30)->get();
        $sessionRecords = InventorySessionRecord::query()->where('property_id', $property->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->with(['item', 'recorder', 'cleaningSession.booking', 'cleaningSession.checkout', 'fromLocation', 'guestLocation'])
            ->latest('created_at')->limit(100)->get();

        return view('admin.inventory.index', compact('properties', 'property', 'items', 'locations', 'movements', 'purchaseOrders', 'sessionRecords'));
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
        $oldPhotoPath = $item->photo_path;
        $newPhotoPath = null;
        if ($request->hasFile('photo')) {
            $newPhotoPath = $request->file('photo')->store('inventory', 'public');
            $data['photo_path'] = $newPhotoPath;
        }
        unset($data['photo']);

        try {
            $item->update($data);
        } catch (\Throwable $exception) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }
            throw $exception;
        }

        if ($newPhotoPath && $oldPhotoPath && $oldPhotoPath !== $newPhotoPath) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

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

    public function storePurchaseOrder(Request $request)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'destination_location_id' => ['required', 'integer'],
            'supplier' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:160'],
            'ordered_at' => ['nullable', 'date'],
            'quantity_ordered' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $property = $this->property($request, (int) $data['property_id']);
        $item = InventoryItem::query()->where('property_id', $property->id)->findOrFail($data['item_id']);
        $location = InventoryLocation::query()->where('property_id', $property->id)->findOrFail($data['destination_location_id']);

        DB::transaction(function () use ($request, $data, $property, $item, $location) {
            $order = InventoryPurchaseOrder::create([
                'property_id' => $property->id,
                'created_by' => $request->user()->id,
                'supplier' => $data['supplier'],
                'reference' => $data['reference'] ?: null,
                'status' => 'ordered',
                'ordered_at' => $data['ordered_at'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);
            if (! $order->reference) {
                $order->update(['reference' => 'PO-'.$order->id]);
            }
            $order->lines()->create([
                'inventory_item_id' => $item->id,
                'destination_location_id' => $location->id,
                'quantity_ordered' => $data['quantity_ordered'],
                'quantity_received' => 0,
                'unit_cost' => $data['unit_cost'] ?? $item->unit_cost,
            ]);
        });

        return back()->with('success', 'Purchase order created. Record each delivery as it arrives.');
    }

    public function receivePurchaseOrder(Request $request, InventoryPurchaseOrder $order, InventoryStockService $stock)
    {
        $property = $this->property($request, (int) $order->property_id);
        $data = $request->validate([
            'line_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
        ]);

        $line = $order->lines()->with(['item', 'destinationLocation'])->findOrFail($data['line_id']);
        abort_unless((int) $line->item->property_id === (int) $property->id
            && (int) $line->destinationLocation->property_id === (int) $property->id, 404);
        abort_if(in_array($order->status, ['received', 'cancelled'], true), 422, 'This purchase order is already closed.');

        $outstanding = round((float) $line->quantity_ordered - (float) $line->quantity_received, 3);
        $quantity = round((float) $data['quantity'], 3);
        if ($quantity > $outstanding + 0.000001) {
            throw ValidationException::withMessages(['quantity' => 'Received quantity cannot exceed the outstanding order quantity.']);
        }

        DB::transaction(function () use ($request, $order, $line, $quantity, $stock) {
            $stock->receive($line->item, $line->destinationLocation, $quantity, $request->user()->id, [
                'reference' => $order->reference,
                'supplier' => $order->supplier,
                'ordered_at' => $order->ordered_at?->toDateString(),
                'notes' => 'Received against purchase order '.$order->reference,
            ]);

            $line->increment('quantity_received', $quantity);
            $line->refresh();
            $line->item->update([
                'supplier' => $order->supplier,
                'last_ordered_at' => $order->ordered_at ?? now()->toDateString(),
                'unit_cost' => $line->unit_cost ?? $line->item->unit_cost,
            ]);

            $lines = $order->lines()->get();
            $allReceived = $lines->every(fn ($row) => (float) $row->quantity_received + 0.000001 >= (float) $row->quantity_ordered);
            $anyReceived = $lines->contains(fn ($row) => (float) $row->quantity_received > 0);
            $order->update(['status' => $allReceived ? 'received' : ($anyReceived ? 'partially_received' : 'ordered')]);
        });

        return back()->with('success', 'Received quantity added to the selected stock location.');
    }

    public function receive(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'location_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'reference' => ['nullable', 'string', 'max:160'],
            'supplier' => ['nullable', 'string', 'max:160'],
            'ordered_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $property = $this->property($request, (int) $data['property_id']);
        $item = InventoryItem::query()->where('property_id', $property->id)->findOrFail($data['item_id']);
        $location = InventoryLocation::query()->where('property_id', $property->id)->findOrFail($data['location_id']);
        $stock->receive($item, $location, (float) $data['quantity'], $request->user()->id, $data);
        $item->update([
            'last_ordered_at' => $data['ordered_at'] ?? now()->toDateString(),
            'supplier' => filled($data['supplier'] ?? null) ? $data['supplier'] : $item->supplier,
        ]);
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


    private function assertInventorySessionAccess(Request $request, CleaningSession $session): void
    {
        $user = $request->user();
        if ($user->hasRole('housekeeper')) {
            abort_unless(
                (int) $session->housekeeper_id === (int) $user->id && $session->status === 'in_progress',
                403,
                'Only your active cleaning session can record inventory.'
            );
            return;
        }

        abort_unless(
            $user->hasAnyRole(['admin', 'owner', 'company', 'manager'])
                && Property::query()->visibleTo($user)->whereKey($session->property_id)->exists(),
            403
        );
        abort_unless($session->status === 'in_progress', 422, 'Inventory can only be recorded during an active cleaning.');
    }

    public function sessionInventory(Request $request, CleaningSession $session)
    {
        $this->assertInventorySessionAccess($request, $session);
        $property = $session->property;
        abort_unless($property, 404);

        $items = InventoryItem::query()->where('property_id', $property->id)->where('active', true)
            ->whereIn('category', ['guest_supply', 'cleaning_supply'])
            ->with('stocks.location')->orderBy('name')->get();
        $locations = InventoryLocation::query()->where('property_id', $property->id)->orderBy('name')->get();
        $records = InventorySessionRecord::query()->where('cleaning_session_id', $session->id)
            ->with(['item', 'fromLocation', 'guestLocation'])->orderBy('id')->get();

        return view('sessions.inventory', compact('session', 'property', 'items', 'locations', 'records'));
    }

    public function recordSessionInventory(Request $request, CleaningSession $session, InventoryStockService $stock)
    {
        $this->assertInventorySessionAccess($request, $session);
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'from_location_id' => ['nullable', 'integer'],
            'guest_location_id' => ['required', 'integer'],
            'quantity_available' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'quantity_taken' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $property = $session->property;
        $item = InventoryItem::query()->where('property_id', $property->id)->where('active', true)->findOrFail($data['item_id']);
        $guestLocation = InventoryLocation::query()->where('property_id', $property->id)->findOrFail($data['guest_location_id']);
        $fromLocation = null;
        if ((float) $data['quantity_taken'] > 0) {
            if (empty($data['from_location_id'])) {
                throw ValidationException::withMessages(['from_location_id' => 'Choose the stock location to take items from.']);
            }
            $fromLocation = InventoryLocation::query()->where('property_id', $property->id)->findOrFail($data['from_location_id']);
            abort_unless(! $fromLocation->is($guestLocation), 422, 'Choose a different stock closet and guest location.');
        }

        DB::transaction(function () use ($request, $session, $item, $guestLocation, $fromLocation, $data, $stock) {
            $previous = InventorySessionRecord::query()
                ->where('inventory_item_id', $item->id)
                ->where('cleaning_session_id', '!=', $session->id)
                ->latest('created_at')->first();

            $current = (float) (\App\Models\InventoryStock::query()
                ->where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $guestLocation->id)
                ->value('quantity') ?? 0);
            $counted = (float) $data['quantity_available'];
            $difference = round($counted - $current, 3);

            if (abs($difference) >= 0.001) {
                $stock->adjust($item, $guestLocation, $difference, $request->user()->id, [
                    'cleaning_session_id' => $session->id,
                    'notes' => 'Cleaner count before replenishment'.(! empty($data['notes']) ? ': '.$data['notes'] : ''),
                ]);
            }

            $taken = (float) $data['quantity_taken'];
            if ($taken > 0 && $fromLocation) {
                $stock->transfer($item, $fromLocation, $guestLocation, $taken, $request->user()->id, [
                    'cleaning_session_id' => $session->id,
                    'notes' => 'Cleaner replenishment'.(! empty($data['notes']) ? ': '.$data['notes'] : ''),
                ]);
            }

            $after = round($counted + $taken, 3);
            $consumed = $previous
                ? max(0, round((float) $previous->quantity_after_restock - $counted, 3))
                : null;

            InventorySessionRecord::query()->updateOrCreate(
                ['cleaning_session_id' => $session->id, 'inventory_item_id' => $item->id],
                [
                    'property_id' => $session->property_id,
                    'from_location_id' => $fromLocation?->id,
                    'guest_location_id' => $guestLocation->id,
                    'recorded_by' => $request->user()->id,
                    'quantity_available' => $counted,
                    'quantity_taken' => $taken,
                    'quantity_after_restock' => $after,
                    'quantity_guest_consumed' => $consumed,
                    'notes' => $data['notes'] ?? null,
                ]
            );
        });

        return redirect()->route('sessions.inventory', $session)->with('success', 'Guest-area stock count saved.');
    }

    public function loss(Request $request, InventoryStockService $stock)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'item_id' => ['required', 'integer'],
            'location_id' => ['required', 'integer'],
            'movement_type' => ['required', Rule::in(['waste', 'theft'])],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'notes' => ['required', 'string', 'max:2000'],
        ]);
        $property = $this->property($request, (int) $data['property_id']);
        $item = InventoryItem::query()->where('property_id', $property->id)->findOrFail($data['item_id']);
        $location = InventoryLocation::query()->where('property_id', $property->id)->findOrFail($data['location_id']);
        $stock->recordLoss($item, $location, (float) $data['quantity'], $data['movement_type'], $request->user()->id, $data);

        return back()->with('success', ucfirst($data['movement_type']).' recorded.');
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
