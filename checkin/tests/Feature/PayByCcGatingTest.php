<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the pay_by_cc opt-in gate: an admin-only checkbox per booking that
 * must be set before the guest sees any Stripe payment UI at all. Unchecked
 * bookings should be refused at the endpoint level, not just hidden in the
 * UI, since a determined guest could otherwise hit the route directly.
 */
class PayByCcGatingTest extends TestCase
{
    use RefreshDatabase;

    private function configureStripe(): void
    {
        config(['services.stripe.key' => 'pk_test_fake', 'services.stripe.secret' => 'sk_test_fake']);
    }

    public function test_deposit_intent_is_refused_when_pay_by_cc_is_false(): void
    {
        $this->configureStripe();
        $property = Property::factory()->create(['deposit_cap_cents' => 5000]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'incidentals_charge' => 40,
            'pay_by_cc' => false,
        ]);

        $response = $this->postJson(route('guest.deposit.intent', [$booking->booking_id, $booking->token]));

        $response->assertStatus(403);
        $response->assertJson(['ok' => false]);
    }

    public function test_deposit_intent_is_refused_when_stripe_not_configured_even_if_pay_by_cc_true(): void
    {
        // Deliberately don't call configureStripe() here.
        $property = Property::factory()->create(['deposit_cap_cents' => 5000]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'incidentals_charge' => 40,
            'pay_by_cc' => true,
        ]);

        $response = $this->postJson(route('guest.deposit.intent', [$booking->booking_id, $booking->token]));

        $response->assertStatus(503);
    }

    public function test_deposit_intent_is_refused_when_already_paid(): void
    {
        $this->configureStripe();
        $booking = Booking::factory()->create([
            'incidentals_charge' => 40,
            'pay_by_cc' => true,
            'deposit_payment_status' => 'captured',
        ]);

        $response = $this->postJson(route('guest.deposit.intent', [$booking->booking_id, $booking->token]));

        $response->assertStatus(422);
    }

    public function test_deposit_intent_is_refused_when_nothing_is_due(): void
    {
        $this->configureStripe();
        $booking = Booking::factory()->create([
            'pay_by_cc' => true,
            // no parking, no incidentals, no early check-in, no cap.
        ]);

        $response = $this->postJson(route('guest.deposit.intent', [$booking->booking_id, $booking->token]));

        $response->assertStatus(422);
    }

    public function test_charge_intent_rejects_unknown_type(): void
    {
        $this->configureStripe();
        $booking = Booking::factory()->create(['pay_by_cc' => true]);

        $response = $this->postJson(
            route('guest.charge.intent', [$booking->booking_id, $booking->token]),
            ['type' => 'not_a_real_type']
        );

        $response->assertStatus(422);
    }

    public function test_charge_intent_for_parking_is_refused_when_nothing_due(): void
    {
        $this->configureStripe();
        $booking = Booking::factory()->create(['pay_by_cc' => true, 'parking_needed' => false]);

        $response = $this->postJson(
            route('guest.charge.intent', [$booking->booking_id, $booking->token]),
            ['type' => 'parking']
        );

        $response->assertStatus(422);
    }

    public function test_paid_card_booking_stays_behind_admin_deposit_verification(): void
    {
        $property = Property::factory()->create();
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'status' => 'awaiting_deposit',
            'identity_confirmed_at' => now(),
            'photo_id_received' => true,
            'approved_at' => now(),
            'background_check_completed_at' => now(),
            'deposit_payment_status' => 'success',
            'pay_by_cc' => true,
        ]);

        $response = $this->get(route('guest.show', [$booking->booking_id, $booking->token]));

        $response->assertOk();
        $response->assertSee('Payment received');
        $response->assertSee('We are confirming your deposit now');
        $response->assertDontSee('Approved for check in!');
    }

    public function test_admin_deposit_verification_releases_paid_booking_to_next_guest_state(): void
    {
        $booking = Booking::factory()->create([
            'status' => 'awaiting_deposit',
            'identity_confirmed_at' => now(),
            'photo_id_received' => true,
            'approved_at' => now(),
            'background_check_completed_at' => now(),
            'deposit_payment_status' => 'success',
            'deposit_verified_at' => now(),
            'pay_by_cc' => true,
        ]);

        $response = $this->get(route('guest.show', [$booking->booking_id, $booking->token]));

        $response->assertOk();
        $response->assertDontSee('Payment received');
    }
}
