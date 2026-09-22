<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 24: Admin-only incidentals charge field (per guest).
 * Plain decimal field, no formula — admin types a number in directly.
 */
class IncidentalsChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_incidentals_charge_defaults_to_null_when_not_set(): void
    {
        $booking = Booking::factory()->create();

        $this->assertNull($booking->fresh()->incidentals_charge);
    }

    public function test_incidentals_charge_can_be_set_directly_with_no_calculation(): void
    {
        $booking = Booking::factory()->create(['incidentals_charge' => 42.50]);

        // Plain field, not derived — persisted value must come back untouched.
        $this->assertSame('42.50', (string) $booking->fresh()->incidentals_charge);
    }

    public function test_incidentals_charge_accepts_zero(): void
    {
        $booking = Booking::factory()->create(['incidentals_charge' => 0]);

        $this->assertSame('0.00', (string) $booking->fresh()->incidentals_charge);
    }

    public function test_incidentals_charge_can_be_updated(): void
    {
        $booking = Booking::factory()->create(['incidentals_charge' => 10]);

        $booking->update(['incidentals_charge' => 99.99]);

        $this->assertSame('99.99', (string) $booking->fresh()->incidentals_charge);
    }

    public function test_incidentals_charge_is_mass_assignable(): void
    {
        // Confirms the field is on the fillable list (admin form relies on this).
        $booking = Booking::factory()->create();
        $booking->fill(['incidentals_charge' => 15])->save();

        $this->assertSame('15.00', (string) $booking->fresh()->incidentals_charge);
    }
}
