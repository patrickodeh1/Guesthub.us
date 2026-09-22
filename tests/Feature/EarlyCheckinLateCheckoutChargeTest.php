<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 26: Early check-in tiers (8am/12pm) + late checkout rates
 * (authorized/unauthorized, hourly). Deliberately decoupled from task 23's
 * auto-checkout command — billing must only ever read the manually-recorded
 * fields, never checked_out_at.
 */
class EarlyCheckinLateCheckoutChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_early_checkin_and_late_checkout_need_approval(): void
    {
        $property = Property::factory()->create([
            'checkin_time' => '16:00',
            'checkout_time' => '10:00',
        ]);
        $booking = Booking::factory()->create(['property_id' => $property->id]);

        $this->assertTrue($booking->requiresCheckinTimeApproval('15:00'));
        $this->assertFalse($booking->requiresCheckinTimeApproval('17:00'));
        $this->assertFalse($booking->requiresCheckinTimeApproval('16:00'));

        $this->assertTrue($booking->requiresCheckoutTimeApproval('11:00'));
        $this->assertFalse($booking->requiresCheckoutTimeApproval('09:00'));
        $this->assertFalse($booking->requiresCheckoutTimeApproval('10:00'));
    }

    // ─── Early check-in billing ─────────────────────────────────────────────

    public function test_early_checkin_charge_is_null_when_no_tier_granted(): void
    {
        $booking = Booking::factory()->create(['early_checkin_tier' => null]);

        $this->assertNull($booking->earlyCheckinCharge());
    }

    public function test_early_checkin_charge_uses_8am_flat_rate(): void
    {
        $property = Property::factory()->create(['early_checkin_rate_8am' => 50]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'early_checkin_tier' => '8am',
        ]);

        $this->assertSame(50.0, $booking->earlyCheckinCharge());
    }

    public function test_early_checkin_charge_uses_12pm_flat_rate(): void
    {
        $property = Property::factory()->create(['early_checkin_rate_12pm' => 25]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'early_checkin_tier' => '12pm',
        ]);

        $this->assertSame(25.0, $booking->earlyCheckinCharge());
    }

    public function test_early_checkin_charge_is_null_when_property_rate_not_set_yet(): void
    {
        $property = Property::factory()->create(['early_checkin_rate_8am' => null]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'early_checkin_tier' => '8am',
        ]);

        $this->assertNull($booking->earlyCheckinCharge());
    }

    public function test_early_checkin_tier_billing_is_independent_of_address_visibility_flag(): void
    {
        // early_checkin (bool, address visibility) and early_checkin_tier
        // (billing) are explicitly decoupled — one being set must not affect
        // the other.
        $property = Property::factory()->create(['early_checkin_rate_8am' => 50]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'early_checkin' => false,
            'early_checkin_tier' => '8am',
        ]);

        $this->assertSame(50.0, $booking->earlyCheckinCharge());
        // early_checkin has no boolean cast on the model, so SQLite returns
        // the raw stored value (0) rather than PHP false — assert falsy,
        // not strictly-false, since that's what the app itself relies on
        // (e.g. Blade's @if($booking->early_checkin) treats 0 as falsy).
        $this->assertFalse((bool) $booking->fresh()->early_checkin);
    }

    // ─── Late checkout billing: authorized ──────────────────────────────────

    public function test_authorized_late_checkout_charge_bills_per_half_hour_block(): void
    {
        // Billing model corrected per client clarification: late checkout is
        // priced per half-hour block, not a flat hours x hourly rate.
        // 3 hours = 6 half-hour blocks x $15 = $90.
        $property = Property::factory()->create(['late_checkout_rate_authorized_per_30min' => 15]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'late_checkout_type' => 'authorized',
            'late_checkout_hours' => 3,
        ]);

        $this->assertSame(90.0, $booking->lateCheckoutCharge());
    }

    public function test_authorized_late_checkout_charge_is_null_without_hours_entered(): void
    {
        $property = Property::factory()->create(['late_checkout_rate_authorized_hourly' => 15]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'late_checkout_type' => 'authorized',
            'late_checkout_hours' => null,
        ]);

        $this->assertNull($booking->lateCheckoutCharge());
    }

    public function test_authorized_late_checkout_charge_is_null_without_property_rate(): void
    {
        $property = Property::factory()->create(['late_checkout_rate_authorized_hourly' => null]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'late_checkout_type' => 'authorized',
            'late_checkout_hours' => 2,
        ]);

        $this->assertNull($booking->lateCheckoutCharge());
    }

    // ─── Late checkout billing: unauthorized ────────────────────────────────

    public function test_unauthorized_late_checkout_hours_computed_from_actual_time_vs_standard(): void
    {
        $property = Property::factory()->create(['checkout_time' => '10:00']);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_out_date' => '2026-08-27',
            'late_checkout_type' => 'unauthorized',
            'late_checkout_actual_time' => '2026-08-27 12:30:00',
        ]);

        $this->assertEqualsWithDelta(2.5, $booking->lateCheckoutHoursUnauthorized(), 0.01);
    }

    public function test_unauthorized_late_checkout_hours_is_zero_when_actual_time_is_before_standard(): void
    {
        // Guest actually left early, not late — must clamp to 0, not go negative.
        $property = Property::factory()->create(['checkout_time' => '10:00']);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_out_date' => '2026-08-27',
            'late_checkout_type' => 'unauthorized',
            'late_checkout_actual_time' => '2026-08-27 09:00:00',
        ]);

        $this->assertSame(0.0, $booking->lateCheckoutHoursUnauthorized());
    }

    public function test_unauthorized_late_checkout_charge_bills_per_half_hour_block(): void
    {
        // 1 hour late = 2 half-hour blocks x $20 = $40.
        $property = Property::factory()->create([
            'checkout_time' => '10:00',
            'late_checkout_rate_unauthorized_per_30min' => 20,
        ]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_out_date' => '2026-08-27',
            'late_checkout_type' => 'unauthorized',
            'late_checkout_actual_time' => '2026-08-27 11:00:00', // 1 hour late
        ]);

        $this->assertSame(40.0, $booking->lateCheckoutCharge());
    }

    public function test_unauthorized_late_checkout_uses_guest_approved_checkout_preference_as_standard(): void
    {
        // If the guest had an approved later checkout time preference, the
        // "standard" baseline used for lateness must respect that, not the
        // property's default checkout time.
        $property = Property::factory()->create(['checkout_time' => '10:00']);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_out_date' => '2026-08-27',
            'checkout_time_preference' => '11:00',
            'checkout_time_status' => 'approved',
            'late_checkout_type' => 'unauthorized',
            'late_checkout_actual_time' => '2026-08-27 11:30:00',
        ]);

        $this->assertEqualsWithDelta(0.5, $booking->lateCheckoutHoursUnauthorized(), 0.01);
    }

    public function test_unauthorized_late_checkout_charge_is_null_without_property_rate(): void
    {
        $property = Property::factory()->create([
            'checkout_time' => '10:00',
            'late_checkout_rate_unauthorized_hourly' => null,
        ]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_out_date' => '2026-08-27',
            'late_checkout_type' => 'unauthorized',
            'late_checkout_actual_time' => '2026-08-27 12:00:00',
        ]);

        $this->assertNull($booking->lateCheckoutCharge());
    }

    public function test_late_checkout_charge_is_null_when_no_type_set(): void
    {
        $booking = Booking::factory()->create(['late_checkout_type' => null]);

        $this->assertNull($booking->lateCheckoutCharge());
    }

    // ─── Deliberate independence from task 23's auto-checkout command ──────

    public function test_late_checkout_billing_ignores_checked_out_at_even_when_auto_checkout_has_fired(): void
    {
        // Simulates the auto-checkout scheduled command having already run
        // and stamped checked_out_at — billing must still be driven only by
        // the manually-recorded late_checkout_actual_time, never by that.
        $property = Property::factory()->create([
            'checkout_time' => '10:00',
            'late_checkout_rate_unauthorized_per_30min' => 10,
        ]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'check_out_date' => '2026-08-27',
            'late_checkout_type' => 'unauthorized',
            'late_checkout_actual_time' => '2026-08-27 11:00:00', // 1 hr late, admin-recorded
            // Auto-checkout fired much later and would imply a very different
            // (larger) lateness if it were mistakenly used for billing.
            'checked_out_at' => '2026-08-27 15:30:00',
            'status' => 'checked_out',
        ]);

        $this->assertEqualsWithDelta(1.0, $booking->lateCheckoutHoursUnauthorized(), 0.01);
        // 1 hour = 2 half-hour blocks x $10 = $20.
        $this->assertSame(20.0, $booking->lateCheckoutCharge());
    }
}
