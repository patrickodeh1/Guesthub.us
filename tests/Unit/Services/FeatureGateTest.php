<?php

namespace Tests\Unit\Services;

use App\Models\Feature;
use App\Models\HostFeature;
use App\Models\User;
use App\Services\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_feature_is_enabled_by_default_but_can_be_explicitly_disabled(): void
    {
        $owner = User::factory()->create();
        Feature::where('key', 'direct_booking_site')->update(['is_free' => true, 'active' => true]);

        $this->assertTrue(FeatureGate::enabled($owner, 'direct_booking_site'));

        HostFeature::create([
            'user_id' => $owner->id,
            'feature_key' => 'direct_booking_site',
            'enabled' => false,
            'source' => 'manual',
        ]);

        $this->assertFalse(FeatureGate::enabled($owner, 'direct_booking_site'));
    }

    public function test_paid_feature_requires_an_enabled_host_record_inside_its_dates(): void
    {
        $owner = User::factory()->create();
        Feature::where('key', 'wheelhouse_pricing')->update([
            'is_free' => false,
            'price_cents' => 1000,
            'active' => true,
        ]);

        $this->assertFalse(FeatureGate::enabled($owner, 'wheelhouse_pricing'));

        HostFeature::create([
            'user_id' => $owner->id,
            'feature_key' => 'wheelhouse_pricing',
            'enabled' => true,
            'source' => 'manual',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMinute(),
        ]);

        $this->assertTrue(FeatureGate::enabled($owner, 'wheelhouse_pricing'));
    }
}
