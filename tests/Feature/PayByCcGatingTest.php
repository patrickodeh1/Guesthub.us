<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\Property;
use App\Models\User;
use App\Services\Payments\PaymentService;
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

    public function test_guest_card_selection_enables_card_payment_for_that_booking(): void
    {
        $booking = Booking::factory()->create([
            'pay_by_cc' => false,
            'platform_payment_selected_at' => now(),
            'incidentals_charge' => 40,
        ]);

        $response = $this->postJson(route('guest.deposit.card', [$booking->booking_id, $booking->token]));

        $response->assertOk()->assertJson(['ok' => true]);
        $booking->refresh();
        $this->assertTrue((bool) $booking->pay_by_cc);
        $this->assertNull($booking->platform_payment_selected_at);
    }

    public function test_guest_deposit_intent_is_blocked_when_host_has_no_connected_stripe_account(): void
    {
        $this->configureStripe();
        $owner = User::factory()->create();
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'deposit_cap_cents' => 5000,
        ]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'incidentals_charge' => 40,
            'pay_by_cc' => true,
        ]);

        $this->postJson(route('guest.deposit.intent', [$booking->booking_id, $booking->token]))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'error' => 'Card payments are not available for this host. Please contact the property owner.']);

        $this->assertDatabaseCount('charges', 0);
    }

    public function test_guest_charge_intent_is_blocked_when_pay_by_cc_is_false(): void
    {
        $this->configureStripe();
        $booking = Booking::factory()->create(['pay_by_cc' => false]);

        $this->postJson(route('guest.charge.intent', [$booking->booking_id, $booking->token]), [
            'type' => 'parking',
        ])->assertStatus(403)->assertJson(['ok' => false]);
    }

    public function test_guest_deposit_intent_is_created_for_the_hosts_connected_account(): void
    {
        $this->configureStripe();
        $owner = User::factory()->create();
        $owner->forceFill([
            'stripe_account_id' => 'acct_host_connected',
            'stripe_charges_enabled' => true,
        ])->save();
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'deposit_cap_cents' => 5000,
        ]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'incidentals_charge' => 40,
            'pay_by_cc' => true,
        ]);

        $payments = \Mockery::mock(PaymentService::class);
        $payments->shouldReceive('isConfigured')->once()->andReturn(true);
        $payments->shouldReceive('createPendingIntent')
            ->once()
            ->withArgs(fn (Booking $record, string $account, string $type, int $amount, string $moment) =>
                $record->id === $booking->id
                && $account === 'acct_host_connected'
                && $type === Charge::TYPE_DEPOSIT
                && $moment === 'precheckin_approval'
            )
            ->andReturn(['client_secret' => 'pi_connected_secret']);
        $this->app->instance(PaymentService::class, $payments);

        $this->postJson(route('guest.deposit.intent', [$booking->booking_id, $booking->token]))
            ->assertOk()
            ->assertJson(['ok' => true, 'client_secret' => 'pi_connected_secret']);
    }

    public function test_guest_itemized_charge_uses_the_hosts_connected_stripe_account(): void
    {
        $this->configureStripe();
        $owner = User::factory()->create();
        $owner->forceFill([
            'stripe_account_id' => 'acct_host_connected',
            'stripe_charges_enabled' => true,
        ])->save();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'incidentals_charge' => 40,
            'pay_by_cc' => true,
        ]);

        $payments = \Mockery::mock(PaymentService::class);
        $payments->shouldReceive('isConfigured')->once()->andReturn(true);
        $payments->shouldReceive('createPendingIntent')
            ->once()
            ->withArgs(fn (Booking $record, string $account, string $type, int $amount, string $moment) =>
                $record->id === $booking->id
                && $account === 'acct_host_connected'
                && $type === Charge::TYPE_INCIDENTALS
                && $amount > 0
                && $moment === 'guest_initiated'
            )
            ->andReturn(['client_secret' => 'pi_itemized_connected_secret']);
        $this->app->instance(PaymentService::class, $payments);

        $this->postJson(route('guest.charge.intent', [$booking->booking_id, $booking->token]), [
            'type' => 'incidentals',
        ])->assertOk()->assertJson([
            'ok' => true,
            'client_secret' => 'pi_itemized_connected_secret',
        ]);
    }

    public function test_guest_itemized_charge_is_blocked_when_host_cannot_accept_card_payments(): void
    {
        $this->configureStripe();
        $owner = User::factory()->create();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'incidentals_charge' => 40,
            'pay_by_cc' => true,
        ]);

        $this->postJson(route('guest.charge.intent', [$booking->booking_id, $booking->token]), [
            'type' => 'incidentals',
        ])->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertDatabaseCount('charges', 0);
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
        config(['services.stripe.secret' => null]);
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
            'deposit_payment_status' => 'success',
        ]);

        $response = $this->postJson(route('guest.deposit.intent', [$booking->booking_id, $booking->token]));

        $response->assertStatus(422);
    }

    public function test_deposit_intent_is_refused_when_nothing_is_due(): void
    {
        $this->configureStripe();
        $owner = User::factory()->create(['stripe_account_id' => 'acct_test', 'stripe_charges_enabled' => true]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'pay_by_cc' => true,
            'additional_deposit_mode' => false,
            'incidentals_charge' => 0,
            // Explicitly no parking, no incidentals, no early check-in, no hold.
        ]);

        $response = $this->postJson(route('guest.deposit.intent', [$booking->booking_id, $booking->token]));

        $response->assertStatus(422);
    }

    public function test_charge_intent_rejects_unknown_type(): void
    {
        $this->configureStripe();
        $owner = User::factory()->create(['stripe_account_id' => 'acct_test', 'stripe_charges_enabled' => true]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'pay_by_cc' => true,
        ]);

        $response = $this->postJson(
            route('guest.charge.intent', [$booking->booking_id, $booking->token]),
            ['type' => 'not_a_real_type']
        );

        $response->assertStatus(422);
    }

    public function test_charge_intent_for_parking_is_refused_when_nothing_due(): void
    {
        $this->configureStripe();
        $owner = User::factory()->create(['stripe_account_id' => 'acct_test', 'stripe_charges_enabled' => true]);
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'pay_by_cc' => true,
            'parking_needed' => false,
        ]);

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
