<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\PropertyAvailability;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\Pms\PriceLabs\PriceLabsIapiClient;
use App\Services\Pms\PriceLabs\PriceLabsIapiSync;
use App\Services\Pms\PriceLabsSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
        $this->assertSame('gh_test_'.$property->id, app(PriceLabsIapiSync::class)->listingId($property));
    }

    public function test_listing_registration_uses_host_email_and_only_documented_required_fields(): void
    {
        config([
            'services.pricelabs.integration_name' => 'GuestHub-Test',
            'services.pricelabs.integration_token' => 'listing-push-token',
            'services.pricelabs.iapi_base_url' => 'https://pricelabs.test/iapi',
        ]);
        $host = User::factory()->create();
        $host->forceFill(['pricelabs_account_email' => 'host-pricelabs@example.test'])->save();
        $property = Property::factory()->create([
            'owner_id' => $host->id,
            'city' => 'Cleveland',
            'country' => 'USA',
        ]);
        $property->listing()->create([
            'listing_slug' => 'pricelabs-listing-test',
            'public_title' => 'PriceLabs Listing',
            'bedrooms' => 2,
        ]);
        Http::fake([
            'pricelabs.test/iapi/listings' => Http::response([
                'successes' => ['gh_test_'.$property->id],
                'errors' => [],
            ], 200),
        ]);

        app(PriceLabsIapiSync::class)->registerListing($property);

        Http::assertSent(function ($request) use ($property): bool {
            $listing = $request['listings'][0] ?? [];

            return str_ends_with($request->url(), '/listings')
                && count($request['listings'] ?? []) === 1
                && ($listing['listing_id'] ?? null) === 'gh_test_'.$property->id
                && ($listing['user_token'] ?? null) === 'host-pricelabs@example.test'
                && ($listing['name'] ?? null) === 'PriceLabs Listing'
                && ($listing['status'] ?? null) === 'available'
                && ($listing['number_of_bedrooms'] ?? null) == 2
                && ! array_key_exists('property_type', $listing);
        });

        $this->assertSame('pms', $property->fresh()->pricelabs_mode);
        $this->assertSame('pricelabs', $property->fresh()->pricing_provider);
    }

    public function test_initial_calendar_push_sends_730_days_for_one_listing(): void
    {
        config([
            'services.pricelabs.integration_name' => 'GuestHub-Test',
            'services.pricelabs.integration_token' => 'calendar-push-token',
            'services.pricelabs.iapi_base_url' => 'https://pricelabs.test/iapi',
        ]);
        $property = Property::factory()->create([
            'pricing_provider' => 'pricelabs',
            'pricelabs_mode' => 'pms',
            'pricelabs_iapi_listing_id' => 'gh_test_calendar_payload',
        ]);
        $property->listing()->create([
            'listing_slug' => 'pricelabs-calendar-test',
            'public_title' => 'Calendar Test',
            'base_rate_cents' => 12550,
        ]);
        Http::fake([
            'pricelabs.test/iapi/calendar' => Http::response([
                'success' => ['gh_test_calendar_payload'],
                'failure' => [],
            ], 200),
        ]);

        app(PriceLabsIapiSync::class)->pushCalendar($property);

        Http::assertSent(function ($request): bool {
            $calendar = $request['calendars'][0] ?? [];

            return str_ends_with($request->url(), '/calendar')
                && count($request['calendars'] ?? []) === 1
                && count($calendar['data'] ?? []) === 730
                && ($calendar['currency'] ?? null) === 'USD'
                && ($calendar['data'][0]['price'] ?? null) === 126
                && array_key_exists('available_units', $calendar['data'][0] ?? [])
                && array_key_exists('booked_units', $calendar['data'][0] ?? [])
                && array_key_exists('blocked_units', $calendar['data'][0] ?? []);
        });
    }

    public function test_reservation_push_maps_required_cost_dates_and_status_fields(): void
    {
        config([
            'services.pricelabs.integration_name' => 'GuestHub-Test',
            'services.pricelabs.integration_token' => 'reservation-push-token',
            'services.pricelabs.iapi_base_url' => 'https://pricelabs.test/iapi',
        ]);
        Queue::fake();
        $property = Property::factory()->create([
            'pricing_provider' => 'pricelabs',
            'pricelabs_mode' => 'pms',
            'pricelabs_iapi_listing_id' => 'gh_test_reservation_payload',
        ]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_in_date' => now()->addDays(5)->toDateString(),
            'check_out_date' => now()->addDays(8)->toDateString(),
            'status' => 'pending',
            'booking_platform' => 'Airbnb',
            'total_cents' => 45000,
            'accommodation_cents' => 40000,
            'cleaning_fee_cents' => 3000,
            'tax_cents' => 2000,
        ]);
        Http::fake([
            'pricelabs.test/iapi/reservations' => Http::response(['success' => [], 'failure' => []], 200),
        ]);

        app(PriceLabsIapiSync::class)->pushReservations($property);

        Http::assertSent(function ($request) use ($booking): bool {
            $reservation = $request['reservations'][0]['data'][0] ?? [];

            return str_ends_with($request->url(), '/reservations')
                && count($request['reservations'] ?? []) === 1
                && ($reservation['reservation_id'] ?? null) === 'gh_'.$booking->property_id.'_booking_'.$booking->id
                && ($reservation['start_date'] ?? null) === $booking->check_in_date->toDateString()
                && ($reservation['end_date'] ?? null) === $booking->check_out_date->toDateString()
                && ($reservation['total_days'] ?? null) === 3
                && ($reservation['total_cost'] ?? null) === 450
                && ($reservation['total_fees'] ?? null) === 30
                && ($reservation['total_taxes'] ?? null) === 20
                && ($reservation['rental_revenue'] ?? null) === 400
                && ($reservation['status'] ?? null) === 'unconfirmed'
                && ($reservation['booking_source'] ?? null) === 'airbnb';
        });
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

    public function test_signed_sync_callback_applies_only_the_matching_listing_and_rejects_bad_signatures(): void
    {
        config(['services.pricelabs.integration_token' => 'callback-test-token']);
        $host = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $host->id,
            'pricing_provider' => 'pricelabs',
            'pricelabs_mode' => 'pms',
            'pricelabs_iapi_listing_id' => 'gh_test_signed_callback',
        ]);
        $body = json_encode([
            'listing_id' => 'gh_test_signed_callback',
            'data' => [[
                'date' => now()->addDays(42)->toDateString(),
                'price' => 212,
                'min_stay' => 3,
            ]],
        ], JSON_THROW_ON_ERROR);
        $signature = app(\App\Services\Pms\PriceLabs\PriceLabsSignature::class);
        $signedHeaders = $signature->signHeaders('pricelabs', 'test-time', 'request-123', 'callback-test-token');
        $signedBody = $signature->signBody($signedHeaders, $body, 'callback-test-token');

        $this->call('POST', route('webhooks.pricelabs.sync'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SOURCE' => 'pricelabs',
            'HTTP_X_PL_TIMESTAMP' => 'test-time',
            'HTTP_X_PL_REQUESTID' => 'request-123',
            'HTTP_X_PL_SIGNED_HEADERS' => $signedHeaders,
            'HTTP_X_PL_SIGNED_BODY' => $signedBody,
        ], $body)->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('property_availabilities', [
            'property_id' => $property->id,
            'date' => now()->addDays(42)->toDateString(),
            'rate' => 212,
            'min_stay_arrival' => 3,
        ]);

        $this->call('POST', route('webhooks.pricelabs.sync'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SOURCE' => 'pricelabs',
            'HTTP_X_PL_TIMESTAMP' => 'test-time',
            'HTTP_X_PL_REQUESTID' => 'request-123',
            'HTTP_X_PL_SIGNED_HEADERS' => $signedHeaders,
            'HTTP_X_PL_SIGNED_BODY' => str_repeat('0', 64),
        ], $body)->assertUnauthorized();
    }

    public function test_pricelabs_callback_verification_probe_returns_http_200_without_signature(): void
    {
        $this->postJson(route('webhooks.pricelabs.sync'), ['verify' => true])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_inbound_iapi_prices_do_not_overwrite_manually_owned_nights(): void
    {
        $property = Property::factory()->create([
            'pricing_provider' => 'pricelabs',
            'pricelabs_mode' => 'pms',
            'pricelabs_iapi_listing_id' => 'gh_test_manual_protection',
        ]);
        $date = now()->addDays(50)->toDateString();

        $manual = new PropertyAvailability([
            'property_id' => $property->id,
            'date' => $date,
            'is_available' => true,
            'status' => 'available',
            'source' => 'manual',
            'rate' => 99,
        ]);
        $manual->forceFill(['rates_source' => 'manual'])->save();

        app(PriceLabsIapiSync::class)->applyForListingId('gh_test_manual_protection', [
            ['date' => $date, 'rate' => 250, 'min_stay' => 2],
        ]);

        $this->assertSame('99.00', (string) $manual->fresh()->rate);
        $this->assertSame('manual', $manual->fresh()->rates_source);
    }

    public function test_non_pricelabs_ledger_changes_queue_calendar_delta_without_echoing_inbound_prices(): void
    {
        Queue::fake();
        $property = Property::factory()->create([
            'pricing_provider' => 'pricelabs',
            'pricelabs_mode' => 'pms',
            'pricelabs_iapi_listing_id' => 'gh_test_calendar_delta',
        ]);
        $date = now()->addDays(61)->toDateString();

        app(\App\Services\Pms\AvailabilityLedger::class)->setDates(
            $property,
            [$date],
            ['rate' => 199],
            'manual',
        );

        Queue::assertPushed(\App\Jobs\PushPriceLabsCalendarToIapi::class, fn ($job) => $job->propertyId === $property->id
            && $job->startDate === $date
            && $job->endDate === $date);

        Queue::fake();
        app(\App\Services\Pms\AvailabilityLedger::class)->setDates(
            $property,
            [$date],
            ['rate' => 205],
            'pricelabs',
        );

        Queue::assertNothingPushed();
    }

    public function test_manual_block_create_and_reopen_queue_reservation_updates(): void
    {
        Queue::fake();
        $property = Property::factory()->create([
            'pricing_provider' => 'pricelabs',
            'pricelabs_mode' => 'pms',
            'pricelabs_iapi_listing_id' => 'gh_test_block_sync',
        ]);
        $date = now()->addDays(75)->toDateString();
        $ledger = app(\App\Services\Pms\AvailabilityLedger::class);

        $ledger->setDates($property, [$date], ['status' => 'blocked'], 'manual');

        Queue::assertPushed(\App\Jobs\PushPriceLabsBookingToIapi::class, fn ($job) => $job->propertyId === $property->id
            && $job->cancelledBlockDates === []);

        Queue::fake();
        $ledger->setDates($property, [$date], ['status' => 'available'], 'manual');

        Queue::assertPushed(\App\Jobs\PushPriceLabsBookingToIapi::class, fn ($job) => $job->propertyId === $property->id
            && $job->cancelledBlockDates === [$date]);
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
