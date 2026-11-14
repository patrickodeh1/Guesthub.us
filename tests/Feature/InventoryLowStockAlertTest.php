<?php

namespace Tests\Feature;

use App\Mail\InventoryLowStockMail;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Property;
use App\Models\User;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InventoryLowStockAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_alert_is_sent_once_and_can_repeat_after_recovery(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $closet = InventoryLocation::create(['property_id' => $property->id, 'name' => 'Supply closet']);
        $item = InventoryItem::create([
            'property_id' => $property->id,
            'name' => 'Toilet paper',
            'category' => 'guest_supply',
            'unit_of_measure' => 'roll',
            'reorder_level' => 2,
        ]);

        $this->artisan('inventory:send-low-stock-alerts')->assertExitCode(0);
        Mail::assertSent(InventoryLowStockMail::class, 1);
        $this->assertNotNull($item->fresh()->low_stock_alerted_at);

        $this->artisan('inventory:send-low-stock-alerts')->assertExitCode(0);
        Mail::assertSent(InventoryLowStockMail::class, 1);

        app(InventoryStockService::class)->receive($item, $closet, 5, $owner->id);
        $this->artisan('inventory:send-low-stock-alerts')->assertExitCode(0);
        $this->assertNull($item->fresh()->low_stock_alerted_at);

        app(InventoryStockService::class)->consume($item, $closet, 4, $owner->id);
        $this->artisan('inventory:send-low-stock-alerts')->assertExitCode(0);
        Mail::assertSent(InventoryLowStockMail::class, 2);
    }

    public function test_item_without_owner_email_does_not_get_marked_as_alerted(): void
    {
        $property = Property::factory()->create(['owner_id' => null]);
        $item = InventoryItem::create([
            'property_id' => $property->id,
            'name' => 'Hand soap',
            'category' => 'guest_supply',
            'reorder_level' => 1,
        ]);

        $this->artisan('inventory:send-low-stock-alerts')->assertExitCode(0);
        $this->assertNull($item->fresh()->low_stock_alerted_at);
    }
}
