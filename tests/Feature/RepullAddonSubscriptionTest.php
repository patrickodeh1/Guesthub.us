<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Package;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\ListingChannelLink;
use App\Services\Billing\BillingQuantityResolver;
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
        Feature::updateOrCreate(['key' => 'repull_channel_sync'], [
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

    public function test_addon_quantity_counts_each_host_listing_once_and_never_counts_another_hosts_listing(): void
    {
        Role::findOrCreate('owner');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $otherOwner = User::factory()->create();
        $otherOwner->assignRole('owner');

        $property = Property::factory()->create(['owner_id' => $owner->id, 'active' => true]);
        $listing = PropertyListing::create([
            'property_id' => $property->id,
            'listing_slug' => 'owner-repull-listing',
            'public_title' => 'Owner listing',
            'repull_listing_id' => 'repull-unique-1',
        ]);
        ListingChannelLink::create(['listing_id' => $listing->id, 'channel' => 'airbnb', 'state' => 'live']);
        ListingChannelLink::create(['listing_id' => $listing->id, 'channel' => 'booking', 'state' => 'published']);

        $otherProperty = Property::factory()->create(['owner_id' => $otherOwner->id, 'active' => true]);
        PropertyListing::create([
            'property_id' => $otherProperty->id,
            'listing_slug' => 'other-owner-repull-listing',
            'public_title' => 'Other owner listing',
            'repull_listing_id' => 'repull-unique-2',
        ]);

        $counts = app(BillingQuantityResolver::class)->counts($owner);
        $this->assertSame(1, $counts['per_channel_listing']);
    }


}
