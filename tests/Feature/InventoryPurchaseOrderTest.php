<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryPurchaseOrder;
use App\Models\InventoryStock;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_can_be_received_in_parts_and_adds_stock_only_when_received(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $closet = InventoryLocation::create(['property_id' => $property->id, 'name' => 'Supply closet']);
        $item = InventoryItem::create([
            'property_id' => $property->id,
            'name' => 'Laundry detergent',
            'category' => 'cleaning_supply',
            'unit_of_measure' => 'bottle',
        ]);

        $this->actingAs($owner)->post(route('admin.inventory.purchase-orders.store'), [
            'property_id' => $property->id,
            'item_id' => $item->id,
            'destination_location_id' => $closet->id,
            'supplier' => 'Walmart',
            'reference' => 'WAL-PO-88',
            'ordered_at' => '2026-11-13',
            'quantity_ordered' => 5,
            'unit_cost' => 8.25,
        ])->assertRedirect();

        $order = InventoryPurchaseOrder::query()->with('lines')->firstOrFail();
        $line = $order->lines->firstOrFail();
        $this->assertSame('ordered', $order->status);
        $this->assertSame(0.0, (float) InventoryStock::query()->where('inventory_item_id', $item->id)->sum('quantity'));

        $this->post(route('admin.inventory.purchase-orders.receive', $order), [
            'line_id' => $line->id,
            'quantity' => 2.5,
        ])->assertRedirect();

        $order->refresh();
        $line->refresh();
        $this->assertSame('partially_received', $order->status);
        $this->assertSame(2.5, (float) $line->quantity_received);
        $this->assertSame(2.5, (float) InventoryStock::query()->where('inventory_item_id', $item->id)->value('quantity'));

        $this->post(route('admin.inventory.purchase-orders.receive', $order), [
            'line_id' => $line->id,
            'quantity' => 2.5,
        ])->assertRedirect();

        $order->refresh();
        $this->assertSame('received', $order->status);
        $this->assertSame(5.0, (float) InventoryStock::query()->where('inventory_item_id', $item->id)->value('quantity'));
        $this->assertSame('Walmart', $item->fresh()->supplier);
        $this->assertSame(8.25, (float) $item->fresh()->unit_cost);
        $this->assertSame(2, InventoryMovement::query()->where('inventory_item_id', $item->id)->count());
        $this->assertSame('WAL-PO-88', InventoryMovement::query()->latest('id')->value('reference'));

        $this->post(route('admin.inventory.purchase-orders.receive', $order), [
            'line_id' => $line->id,
            'quantity' => 0.1,
        ])->assertUnprocessable();
    }
}
