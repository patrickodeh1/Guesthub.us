<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Property;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryStockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_transfer_and_fractional_usage_keep_location_balances_in_sync(): void
    {
        $property = Property::factory()->create();
        $closet = InventoryLocation::create(['property_id' => $property->id, 'name' => 'Supply closet']);
        $bathroom = InventoryLocation::create(['property_id' => $property->id, 'name' => 'Guest bathroom']);
        $item = InventoryItem::create([
            'property_id' => $property->id,
            'name' => 'Toilet paper',
            'category' => 'guest_supply',
            'unit_of_measure' => 'roll',
            'reorder_level' => 2,
        ]);

        $service = app(InventoryStockService::class);
        $service->receive($item, $closet, 10, null, ['reference' => 'PO-100']);
        $service->transfer($item, $closet, $bathroom, 4, null);
        $service->consume($item, $bathroom, 0.5, null, ['notes' => 'Used during turnover']);

        $this->assertSame(6.0, (float) InventoryStock::where('inventory_item_id', $item->id)->where('inventory_location_id', $closet->id)->value('quantity'));
        $this->assertSame(3.5, (float) InventoryStock::where('inventory_item_id', $item->id)->where('inventory_location_id', $bathroom->id)->value('quantity'));
        $this->assertSame(3, InventoryMovement::where('inventory_item_id', $item->id)->count());
        $lastMovement = InventoryMovement::query()->where('inventory_item_id', $item->id)->latest('id')->firstOrFail();
        $this->assertSame('consume', $lastMovement->movement_type);
        $this->assertSame(0.5, (float) $lastMovement->quantity);
    }

    public function test_consumption_cannot_make_a_location_balance_negative(): void
    {
        $property = Property::factory()->create();
        $closet = InventoryLocation::create(['property_id' => $property->id, 'name' => 'Supply closet']);
        $item = InventoryItem::create([
            'property_id' => $property->id,
            'name' => 'Shampoo',
            'category' => 'guest_supply',
            'unit_of_measure' => 'bottle',
        ]);

        $service = app(InventoryStockService::class);
        $service->receive($item, $closet, 1, null);

        try {
            $service->consume($item, $closet, 1.25, null);
            $this->fail('Expected insufficient stock to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }

        $this->assertSame(1.0, (float) InventoryStock::where('inventory_item_id', $item->id)->value('quantity'));
        $this->assertSame(1, InventoryMovement::where('inventory_item_id', $item->id)->count());
    }

    public function test_stock_cannot_be_moved_between_different_properties(): void
    {
        $propertyA = Property::factory()->create();
        $propertyB = Property::factory()->create();
        $closetA = InventoryLocation::create(['property_id' => $propertyA->id, 'name' => 'Closet A']);
        $closetB = InventoryLocation::create(['property_id' => $propertyB->id, 'name' => 'Closet B']);
        $item = InventoryItem::create([
            'property_id' => $propertyA->id,
            'name' => 'Laundry detergent',
            'category' => 'cleaning_supply',
            'unit_of_measure' => 'bottle',
        ]);

        $this->expectException(ValidationException::class);
        app(InventoryStockService::class)->receive($item, $closetB, 1, null);
    }
}
