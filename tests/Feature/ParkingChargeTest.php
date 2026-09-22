<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 25: Auto parking rate calc from per-day property pricing + override.
 * 7 per-weekday rates on the property, auto-summed across the stay's nights;
 * unconfigured days count as $0 rather than blocking the calculation;
 * admin-only override wins over the auto-calculated amount when set.
 */
class ParkingChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_parking_charge_is_null_when_parking_not_needed(): void
    {
        $property = Property::factory()->create(['parking_rate_monday' => 10]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'parking_needed' => false,
            'check_in_date' => '2026-08-24', // Monday
            'check_out_date' => '2026-08-25',
        ]);

        $this->assertNull($booking->calculateParkingCharge());
    }

    public function test_parking_charge_sums_rate_for_each_night_of_the_stay(): void
    {
        // 2026-08-24 is a Monday.
        $property = Property::factory()->create([
            'parking_rate_monday' => 10,
            'parking_rate_tuesday' => 12,
            'parking_rate_wednesday' => 15,
        ]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'parking_needed' => true,
            'check_in_date' => '2026-08-24', // Mon
            'check_out_date' => '2026-08-27', // Thu — 3 nights: Mon, Tue, Wed
        ]);

        // Checkout night itself (Thursday) is not charged — only nights spent.
        $this->assertEqualsWithDelta(37.0, $booking->calculateParkingCharge(), 0.001);
    }

    public function test_unconfigured_weekday_counts_as_zero_not_a_blocker(): void
    {
        $property = Property::factory()->create([
            'parking_rate_monday' => 10,
            'parking_rate_tuesday' => null, // not configured yet
        ]);

        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'parking_needed' => true,
            'check_in_date' => '2026-08-24', // Mon
            'check_out_date' => '2026-08-26', // Wed — 2 nights: Mon, Tue
        ]);

        // Tuesday contributes $0 rather than making the whole calc null.
        $this->assertEqualsWithDelta(10.0, $booking->calculateParkingCharge(), 0.001);
    }

    public function test_all_unconfigured_rates_result_in_zero_not_null(): void
    {
        $property = Property::factory()->create();
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'parking_needed' => true,
            'check_in_date' => '2026-08-24',
            'check_out_date' => '2026-08-26',
        ]);

        $this->assertSame(0.0, $booking->calculateParkingCharge());
    }

    public function test_recalculate_parking_charge_persists_the_value(): void
    {
        $property = Property::factory()->create(['parking_rate_monday' => 20]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'parking_needed' => true,
            'check_in_date' => '2026-08-24',
            'check_out_date' => '2026-08-25',
        ]);

        $booking->recalculateParkingCharge();

        $this->assertSame('20.00', (string) $booking->fresh()->parking_charge);
    }

    public function test_effective_parking_charge_falls_back_to_auto_calculated_amount(): void
    {
        $booking = Booking::factory()->create([
            'parking_charge' => 30,
            'parking_charge_override' => null,
        ]);

        $this->assertSame(30.0, $booking->effectiveParkingCharge());
    }

    public function test_effective_parking_charge_prefers_admin_override(): void
    {
        $booking = Booking::factory()->create([
            'parking_charge' => 30,
            'parking_charge_override' => 5,
        ]);

        $this->assertSame(5.0, $booking->effectiveParkingCharge());
    }

    public function test_effective_parking_charge_override_of_zero_is_respected(): void
    {
        // Edge case: override = 0 is a real admin decision (waived), must not
        // be treated the same as "no override set" (null).
        $booking = Booking::factory()->create([
            'parking_charge' => 30,
            'parking_charge_override' => 0,
        ]);

        $this->assertSame(0.0, $booking->effectiveParkingCharge());
    }

    public function test_effective_parking_charge_is_null_when_nothing_calculated_yet(): void
    {
        $booking = Booking::factory()->create([
            'parking_charge' => null,
            'parking_charge_override' => null,
        ]);

        $this->assertNull($booking->effectiveParkingCharge());
    }

    public function test_parking_rate_for_day_accepts_int_day_of_week(): void
    {
        $property = Property::factory()->create(['parking_rate_saturday' => 25]);

        $this->assertSame(25.0, $property->parkingRateForDay(6)); // 6 = Saturday
        $this->assertNull($property->parkingRateForDay(0)); // Sunday not configured
    }
}
