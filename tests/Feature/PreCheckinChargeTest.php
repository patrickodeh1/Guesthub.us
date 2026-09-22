<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\Property;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers Booking::calculatePreCheckinChargeCents() — the corrected combined
 * charge model per client clarification: parking + incidentals + early
 * check-in (if already granted), capped per-property (or global default),
 * plus a global % processing fee. Also covers unbilledIncidentalsCents(),
 * which prevents double-charging incidentals once some of it was already
 * folded into the combined charge.
 */
class PreCheckinChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_charge_is_zero_when_nothing_is_due(): void
    {
        $booking = Booking::factory()->create();

        $this->assertSame(0, $booking->calculatePreCheckinChargeCents());
    }

    public function test_charge_sums_parking_and_incidentals(): void
    {
        $property = Property::factory()->create(['parking_rate_monday' => 20]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'parking_needed' => true,
            'check_in_date' => '2026-08-24', // Monday
            'check_out_date' => '2026-08-25',
            'incidentals_charge' => 30,
        ]);

        // 20 (parking) + 30 (incidentals) = 50, no cap set, no fee set.
        $this->assertSame(5000, $booking->calculatePreCheckinChargeCents());
    }

    public function test_charge_includes_early_checkin_when_already_granted(): void
    {
        $property = Property::factory()->create(['early_checkin_rate_8am' => 40]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'early_checkin_tier' => '8am',
            'incidentals_charge' => 10,
        ]);

        $this->assertSame(5000, $booking->calculatePreCheckinChargeCents());
    }

    public function test_early_checkin_uses_new_three_tier_windows(): void
    {
        $property = Property::factory()->create([
            'early_checkin_rate_8am_12pm' => 40,
            'early_checkin_rate_12pm_2pm' => 25,
            'early_checkin_rate_2pm_4pm' => 15,
        ]);

        $booking8to12 = Booking::factory()->create(['property_id' => $property->id, 'early_checkin_tier' => '8am_12pm']);
        $booking12to2 = Booking::factory()->create(['property_id' => $property->id, 'early_checkin_tier' => '12pm_2pm']);
        $booking2to4 = Booking::factory()->create(['property_id' => $property->id, 'early_checkin_tier' => '2pm_4pm']);

        $this->assertSame(40.0, $booking8to12->earlyCheckinCharge());
        $this->assertSame(25.0, $booking12to2->earlyCheckinCharge());
        $this->assertSame(15.0, $booking2to4->earlyCheckinCharge());
    }

    public function test_late_checkout_authorized_bills_per_half_hour_block_rounded_up(): void
    {
        $property = Property::factory()->create(['late_checkout_rate_authorized_per_30min' => 10]);

        // 1.2 hours = 72 minutes = 3 half-hour blocks (rounded up from 2.4).
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'late_checkout_type' => 'authorized',
            'late_checkout_hours' => 1.2,
        ]);

        $this->assertSame(30.0, $booking->lateCheckoutCharge());
    }

    public function test_late_checkout_exact_half_hour_does_not_round_up_extra_block(): void
    {
        $property = Property::factory()->create(['late_checkout_rate_authorized_per_30min' => 10]);

        // Exactly 1.0 hour = 2 whole half-hour blocks, no rounding needed.
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'late_checkout_type' => 'authorized',
            'late_checkout_hours' => 1.0,
        ]);

        $this->assertSame(20.0, $booking->lateCheckoutCharge());
    }

    public function test_late_checkout_unauthorized_uses_higher_rate_per_half_hour(): void
    {
        $property = Property::factory()->create([
            'checkout_time' => '10:00',
            'late_checkout_rate_unauthorized_per_30min' => 25,
        ]);
        // 1 hour late = 2 half-hour blocks x $25 = $50.
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_out_date' => '2026-08-27',
            'late_checkout_type' => 'unauthorized',
            'late_checkout_actual_time' => '2026-08-27 11:00:00',
        ]);

        $this->assertSame(50.0, $booking->lateCheckoutCharge());
    }

    public function test_charge_is_capped_at_property_level_cap(): void
    {
        $property = Property::factory()->create([
            'parking_rate_monday' => 100,
            'deposit_cap_cents' => 5000, // $50 cap
        ]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'parking_needed' => true,
            'check_in_date' => '2026-08-24',
            'check_out_date' => '2026-08-25',
            'incidentals_charge' => 200, // way over the cap combined
        ]);

        $this->assertSame(5000, $booking->calculatePreCheckinChargeCents());
    }

    public function test_charge_falls_back_to_global_default_cap_when_property_cap_unset(): void
    {
        Setting::putValue('default_deposit_cap_cents', '3000');
        $property = Property::factory()->create(['deposit_cap_cents' => null]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'incidentals_charge' => 100,
        ]);

        $this->assertSame(3000, $booking->calculatePreCheckinChargeCents());
    }

    public function test_processing_fee_percent_applied_on_top_of_capped_subtotal(): void
    {
        Setting::putValue('processing_fee_percent', '10');
        $property = Property::factory()->create(['deposit_cap_cents' => 10000]); // $100 cap
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'incidentals_charge' => 100, // over the cap, so subtotal = capped $100
        ]);

        // $100 capped subtotal + 10% fee = $110
        $this->assertSame(11000, $booking->calculatePreCheckinChargeCents());
    }

    public function test_zero_cap_means_uncapped(): void
    {
        // A cap of 0/null should not mean "cap everything at $0" — it means
        // no cap is configured at all.
        $property = Property::factory()->create(['deposit_cap_cents' => 0]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'incidentals_charge' => 500,
        ]);

        $this->assertSame(50000, $booking->calculatePreCheckinChargeCents());
    }

    // ─── Double-charge prevention ───────────────────────────────────────────

    public function test_unbilled_incidentals_is_full_amount_when_nothing_billed_yet(): void
    {
        $booking = Booking::factory()->create([
            'incidentals_charge' => 40,
            'incidentals_billed_cents' => 0,
        ]);

        $this->assertSame(4000, $booking->unbilledIncidentalsCents());
    }

    public function test_unbilled_incidentals_is_zero_once_fully_billed(): void
    {
        $booking = Booking::factory()->create([
            'incidentals_charge' => 40,
            'incidentals_billed_cents' => 4000,
        ]);

        $this->assertSame(0, $booking->unbilledIncidentalsCents());
    }

    public function test_unbilled_incidentals_is_only_the_delta_when_admin_increases_it_after_billing(): void
    {
        // Combined charge already captured $40 of incidentals; admin later
        // raises it to $60 — only the extra $20 should be billable post-checkout,
        // not the full $60 again.
        $booking = Booking::factory()->create([
            'incidentals_charge' => 60,
            'incidentals_billed_cents' => 4000,
        ]);

        $this->assertSame(2000, $booking->unbilledIncidentalsCents());
    }

    public function test_unbilled_incidentals_never_goes_negative_if_admin_lowers_it(): void
    {
        $booking = Booking::factory()->create([
            'incidentals_charge' => 20,
            'incidentals_billed_cents' => 4000, // was billed at a higher amount before being lowered
        ]);

        $this->assertSame(0, $booking->unbilledIncidentalsCents());
    }

    public function test_finalize_snapshots_incidentals_billed_cents_on_deposit_capture(): void
    {
        $booking = Booking::factory()->create(['incidentals_charge' => 25]);
        $charge = $booking->charges()->create([
            'type' => Charge::TYPE_DEPOSIT,
            'amount_cents' => 2500,
            'status' => Charge::STATUS_PENDING,
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        // Simulate what PaymentService::finalize() does on successful capture,
        // without hitting the real Stripe API.
        $charge->update(['status' => Charge::STATUS_CAPTURED, 'captured_at' => now()]);
        $booking->update(['incidentals_billed_cents' => (int) round(($booking->incidentals_charge ?? 0) * 100)]);

        $this->assertSame(0, $booking->fresh()->unbilledIncidentalsCents());
    }
}
