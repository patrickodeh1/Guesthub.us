<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Property;
use App\Models\PropertyLock;
use App\Models\PropertyListing;
use App\Models\ListingChannelLink;
use App\Models\User;
use App\Services\Billing\BillingQuantityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingQuantityResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_property_and_per_device_addons_use_the_hosts_real_resource_counts(): void
    {
        Role::findOrCreate('owner');
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $propertyA = Property::factory()->create(['owner_id' => $owner->id, 'active' => true]);
        $propertyB = Property::factory()->create(['owner_id' => $owner->id, 'active' => true]);
        Property::factory()->create(['owner_id' => $owner->id, 'active' => false]);

        PropertyLock::create(['property_id' => $propertyA->id, 'seam_device_id' => 'lock-a', 'label' => 'Front door']);
        PropertyLock::create(['property_id' => $propertyA->id, 'seam_device_id' => 'lock-b', 'label' => 'Back door']);
        PropertyLock::create(['property_id' => $propertyB->id, 'seam_device_id' => '', 'label' => 'Not connected']);

        Feature::create([
            'key' => 'test_inventory_per_property',
            'name' => 'Test per-property add-on',
            'is_free' => false,
            'price_cents' => 500,
            'active' => true,
            'billing_unit' => 'per_property',
        ]);
        Feature::create([
            'key' => 'test_lock_per_device',
            'name' => 'Test per-device add-on',
            'is_free' => false,
            'price_cents' => 500,
            'active' => true,
            'billing_unit' => 'per_device',
        ]);
        Feature::create([
            'key' => 'test_flat_addon',
            'name' => 'Test flat add-on',
            'is_free' => false,
            'price_cents' => 500,
            'active' => true,
            'billing_unit' => 'flat',
        ]);

        $resolver = app(BillingQuantityResolver::class);
        $this->assertSame([
            'per_property' => 2,
            'per_device' => 2,
            'per_channel_listing' => 0,
        ], $resolver->counts($owner));

        $this->assertSame([
            'test_inventory_per_property' => 2,
            'test_lock_per_device' => 2,
            'test_flat_addon' => 1,
        ], $resolver->resolve($owner, [
            'test_inventory_per_property' => 1,
            'test_lock_per_device' => 1,
            'test_flat_addon' => 1,
        ]));
    }

    public function test_per_resource_addon_is_not_billed_when_the_host_has_no_matching_resource(): void
    {
        Role::findOrCreate('owner');
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        Feature::create([
            'key' => 'test_no_device_addon',
            'name' => 'Test no-device add-on',
            'is_free' => false,
            'price_cents' => 500,
            'active' => true,
            'billing_unit' => 'per_device',
        ]);

        $this->assertSame(['test_no_device_addon' => 0], app(BillingQuantityResolver::class)->resolve($owner, [
            'test_no_device_addon' => 1,
        ]));
    }

    public function test_repull_addon_counts_unique_active_channel_listings_only_for_the_host(): void
    {
        Role::findOrCreate('owner');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $other = User::factory()->create();
        $other->assignRole('owner');

        $property = Property::factory()->create(['owner_id' => $owner->id, 'active' => true]);
        $otherProperty = Property::factory()->create(['owner_id' => $other->id, 'active' => true]);

        $listing = PropertyListing::create([
            'property_id' => $property->id,
            'listing_slug' => 'repull-test-'.$property->id,
            'public_title' => 'Test listing',
        ]);
        $listing->forceFill(['repull_listing_id' => 'repull-id-1'])->save();
        ListingChannelLink::create(['listing_id' => $listing->id, 'channel' => 'airbnb', 'state' => 'live']);
        ListingChannelLink::create(['listing_id' => $listing->id, 'channel' => 'booking', 'state' => 'published']);

        $draft = PropertyListing::create([
            'property_id' => $property->id,
            'listing_slug' => 'repull-draft-'.$property->id,
            'public_title' => 'Draft listing',
        ]);
        $draft->forceFill(['repull_listing_id' => 'repull-id-2'])->save();
        ListingChannelLink::create(['listing_id' => $draft->id, 'channel' => 'airbnb', 'state' => 'unlisted']);

        $foreignListing = PropertyListing::create([
            'property_id' => $otherProperty->id,
            'listing_slug' => 'repull-other-'.$otherProperty->id,
            'public_title' => 'Other host listing',
        ]);
        $foreignListing->forceFill(['repull_listing_id' => 'repull-id-3'])->save();
        ListingChannelLink::create(['listing_id' => $foreignListing->id, 'channel' => 'airbnb', 'state' => 'live']);

        $this->assertSame(1, app(BillingQuantityResolver::class)->counts($owner)['per_channel_listing']);
        $this->assertSame(1, app(BillingQuantityResolver::class)->counts($other)['per_channel_listing']);
    }


    public function test_repull_addon_reserves_one_billable_listing_before_first_publish(): void
    {
        Role::findOrCreate('owner');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        Feature::create([
            'key' => 'repull_channel_sync_test',
            'name' => 'Repull channel sync test',
            'is_free' => false,
            'price_cents' => 500,
            'active' => true,
            'billing_unit' => 'per_channel_listing',
        ]);

        $resolver = app(BillingQuantityResolver::class);
        $this->assertSame(1, $resolver->resolve($owner, ['repull_channel_sync_test' => 1])['repull_channel_sync_test']);
    }

}
