<?php

namespace Tests\Feature;

use App\Models\CleaningSession;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventorySessionRecord;
use App\Models\InventoryStock;
use App\Models\Property;
use App\Models\User;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventorySessionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleaner_records_fractional_guest_stock_and_guest_use_across_turnovers(): void
    {
        Role::firstOrCreate(['name' => 'housekeeper', 'guard_name' => 'web']);
        $owner = User::factory()->create();
        $cleaner = User::factory()->create();
        $cleaner->assignRole('housekeeper');
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $closet = InventoryLocation::create(['property_id' => $property->id, 'name' => 'Supply closet']);
        $bathroom = InventoryLocation::create(['property_id' => $property->id, 'name' => 'Guest bathroom']);
        $item = InventoryItem::create([
            'property_id' => $property->id,
            'name' => 'Toilet paper',
            'category' => 'guest_supply',
            'unit_of_measure' => 'roll',
            'reorder_level' => 2,
        ]);
        app(InventoryStockService::class)->receive($item, $closet, 10, $owner->id);

        $first = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'in_progress',
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->actingAs($cleaner)->post(route('sessions.inventory.record', $first), [
            'item_id' => $item->id,
            'from_location_id' => $closet->id,
            'guest_location_id' => $bathroom->id,
            'quantity_available' => 0.5,
            'quantity_taken' => 1.25,
        ])->assertRedirect(route('sessions.inventory', $first));

        $firstRecord = InventorySessionRecord::query()
            ->where('cleaning_session_id', $first->id)
            ->where('inventory_item_id', $item->id)
            ->firstOrFail();
        $this->assertSame(0.5, (float) $firstRecord->quantity_available);
        $this->assertSame(1.25, (float) $firstRecord->quantity_taken);
        $this->assertSame(1.75, (float) $firstRecord->quantity_after_restock);
        $this->assertNull($firstRecord->quantity_guest_consumed);

        $second = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'in_progress',
            'scheduled_date' => now()->addDay()->toDateString(),
        ]);

        $this->post(route('sessions.inventory.record', $second), [
            'item_id' => $item->id,
            'from_location_id' => $closet->id,
            'guest_location_id' => $bathroom->id,
            'quantity_available' => 0.75,
            'quantity_taken' => 0.5,
        ])->assertRedirect(route('sessions.inventory', $second));

        $secondRecord = InventorySessionRecord::query()
            ->where('cleaning_session_id', $second->id)
            ->where('inventory_item_id', $item->id)
            ->firstOrFail();
        $this->assertSame(1.0, (float) $secondRecord->quantity_guest_consumed);
        $this->assertSame(1.25, (float) InventoryStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $bathroom->id)
            ->value('quantity'));
        $this->assertSame(8.25, (float) InventoryStock::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $closet->id)
            ->value('quantity'));
    }

    public function test_cleaner_cannot_record_inventory_for_another_cleaners_session(): void
    {
        Role::firstOrCreate(['name' => 'housekeeper', 'guard_name' => 'web']);
        $owner = User::factory()->create();
        $cleaner = User::factory()->create();
        $otherCleaner = User::factory()->create();
        $cleaner->assignRole('housekeeper');
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => $otherCleaner->id,
            'status' => 'in_progress',
        ]);

        $this->actingAs($cleaner)->get(route('sessions.inventory', $session))->assertForbidden();
    }
}
