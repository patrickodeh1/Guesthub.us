<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Package;
use App\Models\User;
use App\Services\Billing\PlanPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RepullAddonSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_repull_channel_sync_is_selectable_before_first_listing_and_quotes_one_listing_minimum(): void
    {
        Role::findOrCreate('owner');
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $package = Package::create([
            'key' => 'basic-test',
            'name' => 'Basic test',
            'description' => 'Test package',
            'price_cents' => 1000,
            'sort_order' => 1,
            'active' => true,
        ]);
        Feature::create([
            'key' => 'repull_channel_sync',
            'name' => 'OTA channel sync (Repull)',
            'is_free' => false,
            'price_cents' => 500,
            'active' => true,
            'billing_unit' => 'per_channel_listing',
        ]);

        $response = $this->actingAs($owner)->get(route('admin.plan.index', ['package' => $package->id]));
        $response->assertOk();
        $response->assertSee('OTA channel sync (Repull)');
        $response->assertSee('Minimum one Repull listing while enabled; counted once across all channels.');

        preg_match('/<input[^>]*name="addons\\[repull_channel_sync\\]\\[enabled\\]"[^>]*>/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'The Repull add-on checkbox should be present.');
        $this->assertStringNotContainsString('disabled', $matches[0], 'The one-listing minimum should allow activation before the first listing is created.');

        $quote = PlanPricing::quote($package, ['repull_channel_sync' => 1]);
        $addonLine = collect($quote['lines'])->firstWhere('key', 'repull_channel_sync');
        $this->assertNotNull($addonLine);
        $this->assertSame(1, $addonLine['quantity']);
        $this->assertSame(1500, $quote['total_cents']);
    }
}
