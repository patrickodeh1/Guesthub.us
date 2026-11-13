<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\Property;
use App\Models\PropertyLock;
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
}
