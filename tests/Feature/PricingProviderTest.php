<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\Pms\AvailabilityLedger;
use App\Services\Pms\PriceLabsRateSource;
use App\Services\Pms\PriceLabsSync;
use App\Services\Pms\WheelhouseConnectionService;
use App\Services\QuoteService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PricingProviderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Property $property;

    private PropertyListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['name' => 'Rate Host', 'host_name' => 'Rate Host']);
        Role::findOrCreate('owner');
        $this->owner->assignRole('owner');
        $this->property = Property::factory()->create([
            'owner_id' => $this->owner->id,
            'active' => true,
            'address' => '15 Market Street',
            'city' => 'San Diego',
            'state' => 'CA',
            'zip' => '92101',
            'beds' => 2,
            'baths' => '1.5',
        ]);
        $this->listing = PropertyListing::create([
            'property_id' => $this->property->id,
            'listing_slug' => 'pricing-test-'.$this->property->id,
            'public_title' => 'Pricing Test',
            'max_guests' => 4,
            'bedrooms' => 2,
            'base_rate_cents' => 10000,
            'booking_mode' => 'instant',
            'advance_notice_hours' => 0,
            'min_nights' => 1,
        ]);
    }

    public function test_direct_only_pricelabs_rates_are_applied_and_used_in_quotes(): void
    {
        $date = now()->addDays(20)->toDateString();
        config(['services.pricelabs.api_key' => 'platform-key']);
        $this->property->forceFill([
            'pricing_provider' => 'pricelabs',
            'pricelabs_listing_id' => 'pl-101',
            'pricelabs_pms' => 'hostaway',
        ])->save();
        Http::fake([
            'https://api.pricelabs.co/v1/listing_prices' => Http::response([[
                'last_refreshed_at' => '2026-11-10T00:00:00Z',
                'data' => [['date' => $date, 'price' => 145.50, 'min_stay' => 1]],
            ]]),
        ]);

        $result = app(PriceLabsSync::class)->sync($this->property);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(1, $result['changed']);
        $this->assertDatabaseHas('property_availabilities', [
            'property_id' => $this->property->id,
            'date' => $date,
            'rate' => 145.50,
            'rates_source' => 'pricelabs',
        ]);
        $this->assertSame(14550, $this->quote($date)['subtotal_cents']);
    }

    public function test_manual_rates_are_not_overwritten_by_provider_sync(): void
    {
        $date = now()->addDays(21)->toDateString();
        config(['services.pricelabs.api_key' => 'platform-key']);
        $this->property->forceFill([
            'pricing_provider' => 'pricelabs',
            'pricelabs_listing_id' => 'pl-102',
            'pricelabs_pms' => 'hostaway',
        ])->save();
        app(AvailabilityLedger::class)->setDates($this->property, [$date], ['rate' => 89.00], 'manual');
        Http::fake([
            'https://api.pricelabs.co/v1/listing_prices' => Http::response([[
                'last_refreshed_at' => '2026-11-10T00:01:00Z',
                'data' => [['date' => $date, 'price' => 200.00, 'min_stay' => 1]],
            ]]),
        ]);

        $result = app(PriceLabsSync::class)->sync($this->property);

        $this->assertSame('ok', $result['status']);
        $this->assertSame(0, $result['changed']);
        $this->assertDatabaseHas('property_availabilities', [
            'property_id' => $this->property->id,
            'date' => $date,
            'rate' => 89.00,
            'rates_source' => 'manual',
        ]);
        $this->assertSame(8900, $this->quote($date)['subtotal_cents']);
    }

    public function test_channex_rate_plan_gate_and_outbox_behavior_are_preserved(): void
    {
        $date = now()->addDays(22)->toDateString();
        config(['services.pricelabs.api_key' => 'platform-key']);
        $this->property->forceFill([
            'pricing_provider' => 'pricelabs',
            'pricelabs_listing_id' => 'pl-103',
            'pricelabs_pms' => 'hostaway',
            'channex_property_id' => 'ch-property',
            'channex_room_type_id' => 'ch-room',
            'rate_source' => 'external',
        ])->save();
        Http::fake([
            'https://api.pricelabs.co/v1/listing_prices' => Http::response([[
                'last_refreshed_at' => '2026-11-10T00:02:00Z',
                'data' => [['date' => $date, 'price' => 160.00, 'min_stay' => 1]],
            ]]),
        ]);

        $withoutPlan = app(PriceLabsSync::class)->sync($this->property);
        $this->assertSame('skipped', $withoutPlan['status']);
        Http::assertNothingSent();

        $this->property->forceFill(['channex_rate_plan_id' => 'ch-plan'])->save();
        $withPlan = app(PriceLabsSync::class)->sync($this->property);

        $this->assertSame('ok', $withPlan['status']);
        $this->assertSame('guesthub', $this->property->fresh()->rate_source);
        $this->assertDatabaseHas('property_availabilities', [
            'property_id' => $this->property->id,
            'date' => $date,
            'rates_source' => 'pricelabs',
        ]);
        $this->assertDatabaseHas('channex_outbox', [
            'property_id' => $this->property->id,
            'kind' => 'restrictions',
            'date' => $date,
        ]);
    }

    public function test_invalid_provider_key_clears_its_rates_and_quotes_fall_back_to_base_rate(): void
    {
        $date = now()->addDays(23)->toDateString();
        $this->property->forceFill([
            'pricing_provider' => 'pricelabs',
            'pricelabs_listing_id' => 'pl-104',
            'pricelabs_pms' => 'hostaway',
        ])->save();
        app(AvailabilityLedger::class)->setDates($this->property, [$date], ['rate' => 199.00], 'pricelabs');
        $this->owner->forceFill(['pricelabs_api_key' => 'invalid-key'])->save();
        Http::fake([
            'https://api.pricelabs.co/v1/listing_prices' => Http::response(['error' => 'API_KEY_INVALID'], 403),
        ]);

        $result = app(PriceLabsSync::class)->sync($this->property);

        $this->assertSame('unauthorized', $result['status']);
        $this->assertDatabaseHas('property_availabilities', [
            'property_id' => $this->property->id,
            'date' => $date,
            'rate' => null,
            'rates_source' => null,
        ]);
        $this->assertSame(10000, $this->quote($date)['subtotal_cents']);
    }

    public function test_price_labs_key_verification_encrypts_and_hides_the_host_key(): void
    {
        config(['services.pricelabs.api_key' => 'platform-key']);
        Http::fake([
            'https://api.pricelabs.co/v1/listings_minimal' => Http::response([[
                'id' => 'pl-201',
                'pms' => 'hostaway',
                'name' => 'Beach House',
            ]]),
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('admin.properties.availability.verify-pricelabs', $this->property),
            ['pricelabs_api_key' => 'host-secret'],
        );

        $response->assertRedirect()
            ->assertSessionHas('priceLabsListings', [[
                'id' => 'pl-201',
                'pms' => 'hostaway',
                'name' => 'Beach House',
            ]]);
        $this->assertSame('host-secret', $this->owner->fresh()->pricelabs_api_key);
        $stored = DB::table('users')->where('id', $this->owner->id)->value('pricelabs_api_key');
        $this->assertNotSame('host-secret', $stored);
        $this->assertArrayNotHasKey('pricelabs_api_key', $this->owner->fresh()->toArray());
        $this->assertSame('host-secret', app(PriceLabsRateSource::class)->apiKeyFor($this->property));

        $this->actingAs($this->owner)
            ->post(route('admin.properties.availability.save-pricelabs', $this->property), [
                'pricelabs_selection' => 'pl-201|hostaway',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('pl-201', $this->property->fresh()->pricelabs_listing_id);
    }

    public function test_invalid_pricelabs_key_returns_the_specific_api_key_error(): void
    {
        Http::fake([
            'https://api.pricelabs.co/v1/listings_minimal' => Http::response(['error' => 'API_KEY_INVALID'], 403),
        ]);

        $this->actingAs($this->owner)
            ->post(route('admin.properties.availability.verify-pricelabs', $this->property), [
                'pricelabs_api_key' => 'bad-key',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'PriceLabs rejected this API key (403 API_KEY_INVALID). Check the key and try again.');

        $this->assertNull($this->owner->fresh()->pricelabs_api_key);
    }

    public function test_wheelhouse_recommendations_use_the_shared_ledger_and_quote_path(): void
    {
        $date = now()->addDays(24)->toDateString();
        config([
            'services.wheelhouse.partner_api_key' => 'partner-key',
            'services.wheelhouse.base_url' => 'https://wheelhouse.test/ss_api/v1',
        ]);
        $this->owner->forceFill(['wheelhouse_user_api_key' => 'user-key'])->save();
        $this->property->forceFill([
            'pricing_provider' => 'wheelhouse',
            'wheelhouse_listing_id' => 'test-1-2',
            'wheelhouse_channel' => 'airbnb',
        ])->save();
        Http::fake([
            'wheelhouse.test/ss_api/v1/listings/*/price_recommendations*' => Http::response([
                'data' => [['stay_date' => $date, 'price' => 175.25, 'min_stay' => 1]],
            ]),
        ]);

        $result = app(PriceLabsSync::class)->sync($this->property);

        $this->assertSame('ok', $result['status']);
        $this->assertDatabaseHas('property_availabilities', [
            'property_id' => $this->property->id,
            'date' => $date,
            'rate' => 175.25,
            'rates_source' => 'wheelhouse',
        ]);
        $this->assertSame(17525, $this->quote($date)['subtotal_cents']);
        Http::assertSent(fn (ClientRequest $request) => str_contains($request->url(), '/price_recommendations')
            && str_contains($request->url(), 'channel=airbnb')
            && $request->hasHeader('X-Integration-Api-Key', 'partner-key')
            && $request->hasHeader('X-User-API-Key', 'user-key'));
    }

    public function test_guesthub_managed_wheelhouse_connection_registers_user_and_upserts_unique_listing(): void
    {
        config([
            'services.wheelhouse.partner_api_key' => 'partner-key',
            'services.wheelhouse.base_url' => 'https://wheelhouse.test/ss_api/v1',
        ]);
        Http::fake([
            'wheelhouse.test/ss_api/v1/user_registration' => Http::response(['user_api_key' => 'returned-user-key']),
            'wheelhouse.test/ss_api/v1/listings/*' => Http::response(['id' => 'ok']),
        ]);

        app(WheelhouseConnectionService::class)->connectIntegratorListing($this->property);

        $user = $this->owner->fresh();
        $property = $this->property->fresh();
        $this->assertSame('test-'.$user->id, $user->wheelhouse_integrators_user_id);
        $this->assertSame('returned-user-key', $user->wheelhouse_user_api_key);
        $this->assertSame("test-{$user->id}-{$property->id}", $property->wheelhouse_listing_id);
        $this->assertNotSame('returned-user-key', DB::table('users')->where('id', $user->id)->value('wheelhouse_user_api_key'));
        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/user_registration')
            && $request['integrators_user_id'] === 'test-'.$user->id
            && $request->hasHeader('X-Integration-Api-Key', 'partner-key'));
        Http::assertSent(fn (ClientRequest $request) => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/listings/test-'.$user->id.'-'.$property->id)
            && $request['source_user_id'] === 'test-'.$user->id
            && $request->hasHeader('X-User-API-Key', 'returned-user-key'));
    }

    public function test_wheelhouse_recommendations_webhook_triggers_an_immediate_pull(): void
    {
        $date = now()->addDays(25)->toDateString();
        config([
            'services.wheelhouse.partner_api_key' => 'partner-key',
            'services.wheelhouse.base_url' => 'https://wheelhouse.test/ss_api/v1',
            'services.wheelhouse.webhook_secret' => 'webhook-secret',
        ]);
        $this->owner->forceFill([
            'wheelhouse_user_api_key' => 'user-key',
            'wheelhouse_integrators_user_id' => 'test-'.$this->owner->id,
        ])->save();
        $this->property->forceFill([
            'pricing_provider' => 'wheelhouse',
            'wheelhouse_listing_id' => "test-{$this->owner->id}-{$this->property->id}",
        ])->save();
        Http::fake([
            'wheelhouse.test/ss_api/v1/listings/*/price_recommendations*' => Http::response([
                'data' => [['stay_date' => $date, 'price' => 189.00, 'min_stay' => 1]],
            ]),
        ]);

        $this->post(route('webhooks.wheelhouse', ['secret' => 'webhook-secret']), [
            'event' => 'listing_recommendations_updated',
            'listing_id' => $this->property->wheelhouse_listing_id,
            'external_user_id' => $this->owner->wheelhouse_integrators_user_id,
        ])->assertNoContent();

        $this->assertDatabaseHas('property_availabilities', [
            'property_id' => $this->property->id,
            'date' => $date,
            'rate' => 189.00,
            'rates_source' => 'wheelhouse',
        ]);
    }

    public function test_switching_providers_clears_only_old_provider_rates_and_preserves_manual_rates(): void
    {
        $providerDate = now()->addDays(26)->toDateString();
        $manualDate = now()->addDays(27)->toDateString();
        config(['services.wheelhouse.partner_api_key' => 'partner-key']);
        $this->owner->forceFill(['wheelhouse_user_api_key' => 'wheelhouse-user-key'])->save();
        $this->property->forceFill(['pricing_provider' => 'pricelabs'])->save();
        $this->property->forceFill(['wheelhouse_listing_id' => 'existing-wheelhouse-listing'])->save();
        app(AvailabilityLedger::class)->setDates($this->property, [$providerDate], ['rate' => 220.00], 'pricelabs');
        app(AvailabilityLedger::class)->setDates($this->property, [$manualDate], ['rate' => 88.00], 'manual');

        $this->actingAs($this->owner)
            ->post(route('admin.properties.availability.save-pricing-provider', $this->property), [
                'pricing_provider' => 'wheelhouse',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('wheelhouse', $this->property->fresh()->pricing_provider);
        $this->assertDatabaseHas('property_availabilities', [
            'property_id' => $this->property->id,
            'date' => $providerDate,
            'rate' => null,
            'rates_source' => null,
        ]);
        $this->assertDatabaseHas('property_availabilities', [
            'property_id' => $this->property->id,
            'date' => $manualDate,
            'rate' => 88.00,
            'rates_source' => 'manual',
        ]);
        $this->assertSame(10000, $this->quote($providerDate)['subtotal_cents']);
        $this->assertSame(8800, $this->quote($manualDate)['subtotal_cents']);
    }

    private function quote(string $checkIn): array
    {
        return app(QuoteService::class)->quote(
            $this->listing,
            $checkIn,
            Carbon::parse($checkIn)->addDay()->toDateString(),
            1,
            'manual',
        );
    }
}
