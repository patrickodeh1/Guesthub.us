<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\HostPackage;
use App\Models\Package;
use App\Models\Property;
use App\Models\User;
use App\Services\Billing\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SyncBillingResourceQuantitiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_base_plan_keeps_a_zero_price_line_when_all_resource_addons_drop_to_zero(): void
    {
        Role::findOrCreate('owner');
        $owner = User::factory()->create();
        $owner->assignRole('owner');
        $package = Package::create([
            'key' => 'test-free-base',
            'name' => 'Free base',
            'price_cents' => 0,
            'active' => true,
        ]);
        $feature = Feature::create([
            'key' => 'test-free-resource-addon',
            'name' => 'Per-property add-on',
            'is_free' => false,
            'price_cents' => 500,
            'active' => true,
            'billing_unit' => 'per_property',
        ]);
        $plan = HostPackage::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'status' => 'active',
            'billing_source' => 'stripe',
            'stripe_customer_id' => 'cus_free',
            'stripe_subscription_id' => 'sub_free',
        ]);
        \\Illuminate\\Support\\Facades\\DB::table('host_features')->insert([
            'user_id' => $owner->id,
            'feature_key' => $feature->key,
            'enabled' => true,
            'quantity' => 1,
            'source' => 'stripe_addon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = new class($owner->id, $package->id, $feature->key) {
            public string $id = 'sub_free';
            public string $status = 'active';
            public string $customer = 'cus_free';
            public ?int $current_period_end = null;
            public ?int $trial_end = null;
            public bool $cancel_at_period_end = false;
            public object $metadata;
            public object $items;

            public function __construct(private int $userId, private int $packageId, private string $featureKey)
            {
                $this->metadata = new class($userId, $packageId, $featureKey) {
                    public function __construct(private int $userId, private int $packageId, private string $featureKey) {}
                    public function toArray(): array
                    {
                        return [
                            'user_id' => (string) $this->userId,
                            'package_id' => (string) $this->packageId,
                            'addons' => json_encode([$this->featureKey => 0]),
                        ];
                    }
                };
                $this->items = (object) ['data' => []];
            }
        };

        $billing = Mockery::mock(BillingService::class);
        $billing->shouldReceive('changeAddons')
            ->once()
            ->withArgs(function (HostPackage $actualPlan, array $quote, ?Package $actualPackage, array $addons) use ($plan, $package, $feature) {
                return $actualPlan->is($plan)
                    && $actualPackage?->is($package)
                    && collect($quote['lines'])->contains(fn ($line) => $line['type'] === 'package' && $line['unit_cents'] === 0)
                    && ($addons[$feature->key] ?? null) === 0;
            })
            ->andReturn($subscription);
        $this->app->instance(BillingService::class, $billing);

        $this->artisan('billing:sync-resource-quantities')->assertExitCode(0);

        $this->assertDatabaseHas('host_features', [
            'user_id' => $owner->id,
            'feature_key' => $feature->key,
            'enabled' => 0,
            'quantity' => 0,
            'source' => 'stripe_addon',
        ]);
    }

    public function test_existing_subscription_quantity_is_reconciled_to_current_property_count(): void
    {
        Role::findOrCreate('owner');
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $package = Package::create([
            'key' => 'test-basic',
            'name' => 'Basic',
            'price_cents' => 2000,
            'active' => true,
        ]);
        $feature = Feature::create([
            'key' => 'test-per-property',
            'name' => 'Per property add-on',
            'is_free' => false,
            'price_cents' => 500,
            'active' => true,
            'billing_unit' => 'per_property',
        ]);

        Property::factory()->create(['owner_id' => $owner->id, 'active' => true]);
        Property::factory()->create(['owner_id' => $owner->id, 'active' => true]);

        $plan = HostPackage::create([
            'user_id' => $owner->id,
            'package_id' => $package->id,
            'status' => 'active',
            'billing_source' => 'stripe',
            'stripe_customer_id' => 'cus_test',
            'stripe_subscription_id' => 'sub_test',
        ]);

        \Illuminate\Support\Facades\DB::table('host_features')->insert([
            'user_id' => $owner->id,
            'feature_key' => $feature->key,
            'enabled' => true,
            'quantity' => 1,
            'source' => 'stripe_addon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = new class($owner->id, $package->id, $feature->key) {
            public string $id = 'sub_test';
            public string $status = 'active';
            public string $customer = 'cus_test';
            public ?int $current_period_end = null;
            public ?int $trial_end = null;
            public bool $cancel_at_period_end = false;
            public object $metadata;
            public object $items;

            public function __construct(private int $userId, private int $packageId, private string $featureKey)
            {
                $this->metadata = new class($userId, $packageId, $featureKey) {
                    public function __construct(private int $userId, private int $packageId, private string $featureKey) {}
                    public function toArray(): array
                    {
                        return [
                            'user_id' => (string) $this->userId,
                            'package_id' => (string) $this->packageId,
                            'addons' => json_encode([$this->featureKey => 2]),
                        ];
                    }
                };
                $this->items = (object) ['data' => []];
            }
        };

        $billing = Mockery::mock(BillingService::class);
        $billing->shouldReceive('changeAddons')
            ->once()
            ->withArgs(function (HostPackage $actualPlan, array $quote, ?Package $actualPackage, array $addons) use ($plan, $package) {
                return $actualPlan->is($plan)
                    && $actualPackage?->is($package)
                    && collect($quote['lines'])->contains(fn ($line) => $line['key'] === 'test-per-property' && $line['quantity'] === 2)
                    && ($addons['test-per-property'] ?? null) === 2;
            })
            ->andReturn($subscription);
        $this->app->instance(BillingService::class, $billing);

        $this->artisan('billing:sync-resource-quantities')->assertExitCode(0);

        $this->assertDatabaseHas('host_features', [
            'user_id' => $owner->id,
            'feature_key' => $feature->key,
            'quantity' => 2,
            'source' => 'stripe_addon',
        ]);
    }
}
