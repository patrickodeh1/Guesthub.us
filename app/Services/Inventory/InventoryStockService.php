<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    public function receive(InventoryItem $item, InventoryLocation $location, float $quantity, ?int $userId, array $meta = []): InventoryMovement
    {
        return $this->move($item, null, $location, $quantity, 'receive', $userId, $meta);
    }

    public function transfer(InventoryItem $item, InventoryLocation $from, InventoryLocation $to, float $quantity, ?int $userId, array $meta = []): InventoryMovement
    {
        if ($from->is($to)) {
            throw ValidationException::withMessages(['to_location_id' => 'Choose a different destination location.']);
        }

        return $this->move($item, $from, $to, $quantity, 'transfer', $userId, $meta);
    }

    public function consume(InventoryItem $item, InventoryLocation $from, float $quantity, ?int $userId, array $meta = []): InventoryMovement
    {
        return $this->move($item, $from, null, $quantity, 'consume', $userId, $meta);
    }

    public function recordLoss(InventoryItem $item, InventoryLocation $location, float $quantity, string $type, ?int $userId, array $meta = []): InventoryMovement
    {
        if (! in_array($type, ['waste', 'theft'], true)) {
            throw ValidationException::withMessages(['movement_type' => 'Choose waste or theft.']);
        }

        return $this->move($item, $location, null, $quantity, $type, $userId, $meta);
    }

    public function adjust(InventoryItem $item, InventoryLocation $location, float $quantity, ?int $userId, array $meta = []): InventoryMovement
    {
        if ($quantity == 0.0) {
            throw ValidationException::withMessages(['quantity' => 'The adjustment cannot be zero.']);
        }

        return $this->move($item, $quantity < 0 ? $location : null, $quantity > 0 ? $location : null, abs($quantity), 'adjustment', $userId, $meta);
    }

    private function move(InventoryItem $item, ?InventoryLocation $from, ?InventoryLocation $to, float $quantity, string $type, ?int $userId, array $meta): InventoryMovement
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be greater than zero.']);
        }

        foreach ([$from, $to] as $location) {
            if ($location && (int) $location->property_id !== (int) $item->property_id) {
                throw ValidationException::withMessages(['location' => 'The selected location does not belong to this property.']);
            }
        }

        return DB::transaction(function () use ($item, $from, $to, $quantity, $type, $userId, $meta) {
            $balances = [];
            foreach (array_filter([$from, $to]) as $location) {
                $balance = InventoryStock::query()->firstOrCreate(
                    ['inventory_item_id' => $item->id, 'inventory_location_id' => $location->id],
                    ['quantity' => 0]
                );
                $balances[$location->id] = InventoryStock::query()->whereKey($balance->id)->lockForUpdate()->first();
            }

            if ($from) {
                $source = $balances[$from->id];
                if ((float) $source->quantity + 0.000001 < $quantity) {
                    throw ValidationException::withMessages(['quantity' => 'There is not enough stock at the selected location.']);
                }
                $source->decrement('quantity', $quantity);
            }

            if ($to) {
                $balances[$to->id]->increment('quantity', $quantity);
            }

            return InventoryMovement::create([
                'property_id' => $item->property_id,
                'inventory_item_id' => $item->id,
                'from_location_id' => $from?->id,
                'to_location_id' => $to?->id,
                'recorded_by' => $userId,
                'cleaning_session_id' => $meta['cleaning_session_id'] ?? null,
                'movement_type' => $type,
                'quantity' => $quantity,
                'reference' => $meta['reference'] ?? null,
                'supplier' => $meta['supplier'] ?? null,
                'ordered_at' => $meta['ordered_at'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'occurred_at' => $meta['occurred_at'] ?? now(),
            ]);
        });
    }
}
