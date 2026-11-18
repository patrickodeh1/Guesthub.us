<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use App\Services\Pms\PriceLabs\PriceLabsIapiClient;
use App\Services\Pms\PriceLabs\PriceLabsIapiSync;
use App\Services\Pms\PriceLabsSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PriceLabsIapiTest extends TestCase
{
    use RefreshDatabase;

    public function test_iapi_is_disabled_until_all_three_credentials_are_configured(): void
    {
        config([
            'services.pricelabs.integration_name' => 'GuestHub',
            'services.pricelabs.integration_token' => 'token',
            'services.pricelabs.iapi_base_url' => null,
        ]);

        $this->assertFalse(app(PriceLabsIapiClient::class)->enabled());
    }

    public function test_iapi_client_sends_both_integration_headers_to_an_allowlisted_endpoint(): void
    {
        config([
            'services.pricelabs.integration_name' => 'GuestHub-Test',
            'services.pricelabs.integration_token' => 'test-integration-token',
            'services.pricelabs.iapi_base_url' => 'https://pricelabs.test/iapi',
        ]);
        Http::fake([
            'pricelabs.test/iapi/listings' => Http::response(['ok' => true], 200),
        ]);

        $result = app(PriceLabsIapiClient::class)->listings('POST', ['probe' => true]);

        $this->assertSame(['ok' => true], $result);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/listings')
            && $request->hasHeader('X-INTEGRATION-NAME', 'GuestHub-Test')
            && $request->hasHeader('X-INTEGRATION-TOKEN', 'test-integration-token')
            && $request['probe'] === true);
    }

    public function test_iapi_client_supports_sync_status_and_rate_plans_endpoints(): void
    {
        config([
            'services.pricelabs.integration_name' => 'GuestHub-Test',
            'services.pricelabs.integration_token' => 'test-integration-token',
            'services.pricelabs.iapi_base_url' => 'https://pricelabs.test/iapi',
        ]);
        Http::fake([
            'pricelabs.test/iapi/sync_status*' => Http::response(['status' => 'ready'], 200),
            'pricelabs.test/iapi/rate_plans*' => Http::response(['rate_plans' => []], 200),
        ]);

        $this->assertSame(['status' => 'ready'], app(PriceLabsIapiClient::class)->syncStatus());
        $this->assertSame(['rate_plans' => []], app(PriceLabsIapiClient::class)->ratePlans('GET'));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sync_status'));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/rate_plans'));
    }

    public function test_iapi_listing_ids_are_unique_and_environment_scoped(): void
    {
        $property = Property::factory()->create();
        $this->assertSame('gh-test-'.$property->id, app(PriceLabsIapiSync::class)->listingId($property));
    }

    public function test_inbound_normalized_prices_only_update_the_matching_hosts_property(): void
    {
        $hostA = User::factory()->create();
        $hostB = User::factory()->create();
        $propertyA = Property::factory()->create([
            'owner_id' => $hostA->id,
            'pricing_provider' => 'pricelabs',
            'pricelabs_mode' => 'pms',
            'pricelabs_iapi_listing_id' => 'gh-test-property-a',
        ]);
        $propertyB = Property::factory()->create([
            'owner_id' => $hostB->id,
            'pricing_provider' => 'pricelabs',
            'pricelabs_mode' => 'pms',
            'pricelabs_iapi_listing_id' => 'gh-test-property-b',
        ]);
        $date = now()->addDays(35)->toDateString();

        $result = app(PriceLabsIapiSync::class)->applyForListingId('gh-test-property-a', [
            ['date' => $date, 'rate' => 187.50, 'min_stay' => 2],
        ]);

        $this->assertSame('ok', $result['status']);
        $this->assertDatabaseHas('property_availabilities', [
            'property_id' => $propertyA->id,
            'date' => $date,
            'rate' => 187.50,
            'min_stay_arrival' => 2,
            'rates_source' => 'pricelabs',
        ]);
        $this->assertDatabaseMissing('property_availabilities', [
            'property_id' => $propertyB->id,
            'date' => $date,
        ]);
        $this->assertNotNull($propertyA->fresh()->pricelabs_iapi_last_price_received_at);
        $this->assertNull($propertyB->fresh()->pricelabs_iapi_last_price_received_at);
    }

    public function test_key_based_pricelabs_polling_is_skipped_for_pms_mode(): void
    {
        $property = Property::factory()->create([
            'pricing_provider' => 'pricelabs',
            'pricelabs_mode' => 'pms',
            'pricelabs_iapi_listing_id' => 'gh-test-no-poll',
        ]);

        $result = app(PriceLabsSync::class)->fetch($property);

        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('polling is disabled', $result['message']);
        Http::assertNothingSent();
    }
}
