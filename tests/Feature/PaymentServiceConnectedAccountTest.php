<?php

namespace Tests\\Feature;

use App\\Models\\Booking;
use App\\Models\\LeaseApplication;
use App\\Services\\Payments\\PaymentService;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class PaymentServiceConnectedAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_guest_intent_cannot_be_created_without_a_connected_account(): void
    {
        $booking = Booking::factory()->create();

        $this->expectException(\\LogicException::class);
        app(PaymentService::class)->createPendingIntent(
            $booking,
            '',
            'incidentals',
            1000,
            'guest_initiated',
        );
    }

    public function test_direct_booking_intent_cannot_be_created_without_a_connected_account(): void
    {
        $booking = Booking::factory()->create();

        $this->expectException(\\LogicException::class);
        app(PaymentService::class)->createDirectBookingIntent($booking, '');
    }

    public function test_lease_application_intent_cannot_be_created_without_a_connected_account(): void
    {
        $this->expectException(\\LogicException::class);
        app(PaymentService::class)->createLeaseApplicationIntent(new LeaseApplication(), '');
    }

    public function test_confirmed_guest_charge_cannot_be_created_without_a_connected_account(): void
    {
        $booking = Booking::factory()->create();

        $this->expectException(\\LogicException::class);
        app(PaymentService::class)->charge($booking, '', 'incidentals', 1000, 'pm_test', 'guest_initiated');
    }
}
