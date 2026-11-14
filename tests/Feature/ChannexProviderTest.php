<?php

namespace Tests\Feature;

use App\Services\Pms\ChannexProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChannexProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.channex.base_url' => 'https://channex.test/api/v1',
            'services.channex.api_key' => 'test-channex-key',
        ]);
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
