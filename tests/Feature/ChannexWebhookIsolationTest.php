<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Services\Pms\BookingImportService;
use App\Services\Pms\PmsBooking;
use App\Services\Pms\PmsProviderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChannexWebhookIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_revision_for_one_hosts_property_does_not_change_another_hosts_booking(): void
    {
        $hostA = User::factory()->create();
        $hostB = User::factory()->create();
        $propertyA = Property::factory()->create([
            'owner_id' => $hostA->id,
            'channex_property_id' => 'channex-property-a',
        ]);
        $propertyB = Property::factory()->create([
            'owner_id' => $hostB->id,
            'channex_property_id' => 'channex-property-b',
        ]);
        $checkIn = now()->addDays(20)->toDateString();
        $checkOut = now()->addDays(23)->toDateString();

        $bookingA = Booking::factory()->create([
            'property_id' => $propertyA->id,
            'source' => 'channex',
            'channex_booking_id' => 'channex-booking-a',
            'guest_name' => 'Host A Guest',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
        ]);
        $bookingB = Booking::factory()->create([
            'property_id' => $propertyB->id,
            'source' => 'channex',
            'channex_booking_id' => 'channex-booking-b',
            'guest_name' => 'Host B Guest',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
        ]);

        $provider = Mockery::mock(PmsProviderInterface::class);
        $provider->shouldReceive('handleWebhookPayload')->once()->andReturn(new PmsBooking(
            externalBookingId: 'channex-booking-a',
            externalPropertyId: 'channex-property-a',
            guestName: 'Host A Guest',
            guestEmail: null,
            guestPhone: null,
            checkInDate: $checkIn,
            checkOutDate: $checkOut,
            status: 'new',
            revisionId: 'revision-a',
        ));
        $provider->shouldReceive('acknowledgeBooking')->once()->with('revision-a');
        $this->app->instance(PmsProviderInterface::class, $provider);
        $this->app->instance(BookingImportService::class, new BookingImportService($provider, 'channex'));

        config(['services.channex.webhook_secret' => 'test-channex-webhook-secret']);

        $this->withHeader('X-Channex-Webhook-Secret', 'test-channex-webhook-secret')
            ->postJson(route('webhooks.channex'), [
            'event' => 'booking_new',
            'payload' => [
                'booking_id' => 'channex-booking-a',
                'property_id' => 'channex-property-a',
                'revision_id' => 'revision-a',
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame($propertyA->id, $bookingA->fresh()->property_id);
        $this->assertSame('Host A Guest', $bookingA->fresh()->guest_name);
        $this->assertSame($propertyB->id, $bookingB->fresh()->property_id);
        $this->assertSame('Host B Guest', $bookingB->fresh()->guest_name);
        $this->assertSame($checkIn, $bookingB->fresh()->check_in_date->toDateString());
    }
}
