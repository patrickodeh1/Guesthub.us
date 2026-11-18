<?php

namespace Tests\Feature;

use App\Services\Pms\ChannexProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannexProviderTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.channex.base_url' => 'https://channex.test/api/v1',
            'services.channex.api_key' => 'test-channex-key',
        ]);
    }

    public function test_create_property_uses_documented_property_payload_and_user_api_key(): void
    {
        Http::fake([
            'https://channex.test/api/v1/properties' => Http::response([
                'data' => ['id' => 'ch-property-1', 'attributes' => ['id' => 'ch-property-1']],
            ], 201),
        ]);

        $result = app(ChannexProvider::class)->createProperty([
            'title' => 'GuestHub Test Home',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'property_type' => 'apartment',
        ], 'guesthub:1');

        $this->assertSame('ch-property-1', $result['data']['id']);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/properties')
            && $request->hasHeader('user-api-key', 'test-channex-key')
            && $request['property']['title'] === 'GuestHub Test Home'
            && $request['property']['currency'] === 'USD');
    }

    public function test_inventory_calls_are_written_to_channex_push_logs(): void
    {
        Http::fake([
            'https://channex.test/api/v1/properties' => Http::response([
                'data' => ['id' => 'ch-property-log'],
            ], 201),
        ]);

        app(ChannexProvider::class)->createProperty([
            'title' => 'Log Test Home',
            'currency' => 'USD',
        ], 'guesthub:42');

        $this->assertDatabaseHas('channex_push_logs', [
            'channex_property_id' => 'guesthub:42',
            'path' => '/properties',
            'value_count' => 1,
            'status' => 201,
            'success' => true,
        ]);
    }

    public function test_create_room_type_and_rate_plan_use_channex_documented_envelopes(): void
    {
        Http::fake([
            'https://channex.test/api/v1/room_types' => Http::response(['data' => ['id' => 'room-1']], 201),
            'https://channex.test/api/v1/rate_plans' => Http::response(['data' => ['id' => 'rate-1']], 201),
        ]);

        app(ChannexProvider::class)->createRoomType([
            'property_id' => 'property-1',
            'title' => 'GuestHub Home',
            'count_of_rooms' => 1,
            'occ_adults' => 2,
            'occ_children' => 0,
            'occ_infants' => 0,
            'default_occupancy' => 2,
        ], 'property-1');
        app(ChannexProvider::class)->createRatePlan([
            'title' => 'Best Available Rate',
            'property_id' => 'property-1',
            'room_type_id' => 'room-1',
            'currency' => 'USD',
            'sell_mode' => 'per_room',
            'rate_mode' => 'manual',
            'options' => [['occupancy' => 2, 'is_primary' => true, 'rate' => 0]],
        ], 'property-1');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/room_types')
            && $request['room_type']['property_id'] === 'property-1'
            && $request['room_type']['occ_adults'] === 2);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/rate_plans')
            && $request['rate_plan']['room_type_id'] === 'room-1'
            && $request['rate_plan']['options'][0]['is_primary'] === true);
    }

    public function test_one_time_token_is_scoped_to_property_and_never_written_to_push_log(): void
    {
        Http::fake([
            'https://channex.test/api/v1/auth/one_time_token' => Http::response([
                'data' => ['token' => 'single-use-token'],
            ], 200),
        ]);

        $token = app(ChannexProvider::class)->createOneTimeToken('property-1', 'Host User');

        $this->assertSame('single-use-token', $token);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/auth/one_time_token')
            && $request['one_time_token']['property_id'] === 'property-1'
            && $request['one_time_token']['username'] === 'Host User');
    }

    public function test_verify_connection_reports_environment_without_exposing_key(): void
    {
        Http::fake(['https://channex.test/api/v1/properties*' => Http::response(['data' => []], 200)]);

        $result = app(ChannexProvider::class)->verifyConnection();

        $this->assertSame(['ok' => true, 'status' => 200, 'environment' => 'production'], $result);
    }

    public function test_booking_revision_feed_normalizes_ota_and_keeps_revision_id_separate(): void
    {
        Http::fake([
            'https://channex.test/api/v1/booking_revisions/feed*' => Http::response([
                'data' => [[
                    'id' => 'revision-123',
                    'attributes' => [
                        'id' => 'revision-123',
                        'booking_id' => 'booking-456',
                        'property_id' => 'property-789',
                        'arrival_date' => '2026-12-10',
                        'departure_date' => '2026-12-14',
                        'status' => 'new',
                        'ota_name' => 'Booking.com',
                        'ota_reservation_code' => 'BC-1001',
                        'customer' => [
                            'name' => 'Taylor',
                            'surname' => 'Guest',
                            'mail' => 'taylor@example.test',
                            'phone' => '+15551234567',
                        ],
                    ],
                ], [
                    'id' => 'revision-vrbo-234',
                    'attributes' => [
                        'id' => 'revision-vrbo-234',
                        'booking_id' => 'booking-vrbo-567',
                        'property_id' => 'property-789',
                        'arrival_date' => '2026-12-18',
                        'departure_date' => '2026-12-21',
                        'status' => 'new',
                        'ota_name' => 'VRBO',
                        'ota_reservation_code' => 'VR-2002',
                        'customer' => ['name' => 'Morgan', 'surname' => 'Traveler'],
                    ],
                ]],
            ], 200),
        ]);

        $bookings = app(ChannexProvider::class)->getBookings();

        $this->assertCount(2, $bookings);
        $this->assertSame('booking-456', $bookings[0]->externalBookingId);
        $this->assertSame('property-789', $bookings[0]->externalPropertyId);
        $this->assertSame('revision-123', $bookings[0]->revisionId);
        $this->assertSame('Booking.com', $bookings[0]->otaName);
        $this->assertSame('BC-1001', $bookings[0]->otaReservationCode);
        $this->assertSame('Taylor Guest', $bookings[0]->guestName);
        $this->assertSame('VRBO', $bookings[1]->otaName);
        $this->assertSame('booking-vrbo-567', $bookings[1]->externalBookingId);

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/booking_revisions/feed')
            && $request->hasHeader('user-api-key'));
    }

    public function test_availability_is_pushed_to_the_dedicated_availability_endpoint(): void
    {
        Http::fake([
            'https://channex.test/api/v1/availability' => Http::response(['success' => true], 200),
        ]);

        $result = app(ChannexProvider::class)->pushAvailability(
            'property-789',
            'room-123',
            ['2026-12-10' => true, '2026-12-11' => false],
        );

        $this->assertTrue($result);
        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/availability')) {
                return false;
            }

            $values = $request['values'];

            return count($values) === 2
                && $values[0]['property_id'] === 'property-789'
                && $values[0]['room_type_id'] === 'room-123'
                && $values[0]['availability'] === 1
                && $values[1]['availability'] === 0
                && $request->hasHeader('user-api-key', 'test-channex-key');
        });
    }

    public function test_acknowledgement_uses_revision_id_not_booking_id(): void
    {
        Http::fake([
            'https://channex.test/api/v1/booking_revisions/revision-123/ack' => Http::response([], 200),
        ]);

        app(ChannexProvider::class)->acknowledgeBooking('revision-123');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/booking_revisions/revision-123/ack'));
    }
}
