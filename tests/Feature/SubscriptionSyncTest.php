<?php

namespace Tests\Feature;

use App\Models\Feature;
use App\Models\HostPackage;
use App\Models\Package;
use App\Models\User;
use App\Services\Billing\SubscriptionSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_events_sync_status_and_addon_entitlements(): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'key' => 'sync-basic',
            'name' => 'Sync Basic',
            'price_cents' => 2000,
            'active' => true,
        ]);
        $feature = Feature::create([
            'key' => 'sync-addon',
            'name' => 'Sync add-on',
            'is_free' => false,
            'price_cents' => 500,
            'active' => true,
            'billing_unit' => 'per_property',
        ]);
        $plan = HostPackage::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'incomplete',
            'billing_source' => 'stripe',
            'stripe_subscription_id' => 'sub_sync_test',
        ]);

        SubscriptionSync::handle($this->subscription(
            'sub_sync_test',
            'active',
            $user->id,
            $package->id,
            [$feature->key => 3],
        ));

        $plan->refresh();
        $this->assertSame('active', $plan->status);
        $this->assertSame('cus_sync_test', $plan->stripe_customer_id);
        $this->assertDatabaseHas('host_features', [
            'user_id' => $user->id,
            'feature_key' => $feature->key,
            'enabled' => 1,
            'quantity' => 3,
            'source' => 'stripe_addon',
        ]);

        SubscriptionSync::handle($this->subscription(
            'sub_sync_test',
            'canceled',
            $user->id,
            $package->id,
            [$feature->key => 3],
        ));

        $this->assertSame('canceled', $plan->fresh()->status);
        $this->assertDatabaseMissing('host_features', [
            'user_id' => $user->id,
            'feature_key' => $feature->key,
            'source' => 'stripe_addon',
        ]);
    }

    public function test_admin_managed_plan_is_not_overwritten_by_stripe_events(): void
    {
        $user = User::factory()->create();
        $package = Package::create([
            'key' => 'admin-basic',
            'name' => 'Admin Basic',
            'price_cents' => 2000,
            'active' => true,
        ]);
        $plan = HostPackage::create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'status' => 'active',
            'billing_source' => 'admin',
            'stripe_subscription_id' => 'sub_admin_test',
        ]);

        SubscriptionSync::handle($this->subscription(
            'sub_admin_test',
            'canceled',
            $user->id,
            $package->id,
            [],
        ));

        $this->assertSame('active', $plan->fresh()->status);
        $this->assertSame('admin', $plan->fresh()->billing_source);
    }

    private function subscription(string $id, string $status, int $userId, int $packageId, array $addons): object
    {
        return new class($id, $status, $userId, $packageId, $addons) {
            public string $id;
            public string $status;
            public string $customer = 'cus_sync_test';
            public ?int $current_period_end = null;
            public ?int $trial_end = null;
            public bool $cancel_at_period_end = false;
            public object $metadata;
            public object $items;

            public function __construct(string $id, string $status, int $userId, int $packageId, array $addons)
            {
                $this->id = $id;
                $this->status = $status;
                $this->metadata = new class($userId, $packageId, $addons) {
                    public function __construct(private int $userId, private int $packageId, private array $addons) {}
                    public function toArray(): array
                    {
                        return [
                            'user_id' => (string) $this->userId,
                            'package_id' => (string) $this->packageId,
                            'addons' => json_encode($this->addons),
                        ];
                    }
                };
                $this->items = (object) ['data' => []];
            }
        };
    }
}
