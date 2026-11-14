<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\User;
use App\Services\CleaningFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleaningGuestLinkResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->property = Property::factory()->create(['owner_id' => $this->owner->id]);
    }

    public function test_it_resolves_a_unique_checkout_booking(): void
    {
        $booking = $this->booking('2026-11-10', '2026-11-14');

        $this->assertSame(
            $booking->id,
            CleaningFlowService::bookingForPropertyDate($this->property->id, '2026-11-14')?->id
        );
    }

    public function test_it_does_not_guess_between_multiple_checkout_bookings(): void
    {
        $this->booking('2026-11-10', '2026-11-14');
        $this->booking('2026-11-11', '2026-11-14');

        $this->assertNull(
            CleaningFlowService::bookingForPropertyDate($this->property->id, '2026-11-14')
        );
    }

    public function test_it_resolves_a_unique_stay_when_there_is_no_checkout_that_day(): void
    {
        $booking = $this->booking('2026-11-10', '2026-11-16');

        $this->assertSame(
            $booking->id,
            CleaningFlowService::bookingForPropertyDate($this->property->id, '2026-11-14')?->id
        );
    }

    public function test_it_does_not_guess_between_overlapping_stays(): void
    {
        $this->booking('2026-11-10', '2026-11-16');
        $this->booking('2026-11-12', '2026-11-18');

        $this->assertNull(
            CleaningFlowService::bookingForPropertyDate($this->property->id, '2026-11-14')
        );
    }

    public function test_legacy_unlinked_cleaning_is_visible_only_to_the_unique_checkout_guest(): void
    {
        $booking = $this->booking('2026-11-10', '2026-11-14');
        $session = CleaningSession::factory()->create([
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'scheduled_date' => '2026-11-14',
            'booking_id' => null,
            'no_guest' => false,
        ]);

        $this->assertTrue($booking->linkedCleanings()->whereKey($session->id)->exists());
    }

    public function test_legacy_unlinked_cleaning_is_not_shown_under_overlapping_guests(): void
    {
        $first = $this->booking('2026-11-10', '2026-11-14');
        $second = $this->booking('2026-11-11', '2026-11-14');
        $session = CleaningSession::factory()->create([
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'scheduled_date' => '2026-11-14',
            'booking_id' => null,
            'no_guest' => false,
        ]);

        $this->assertFalse($first->linkedCleanings()->whereKey($session->id)->exists());
        $this->assertFalse($second->linkedCleanings()->whereKey($session->id)->exists());
    }

    public function test_auto_tag_leaves_ambiguous_cleaning_unlinked(): void
    {
        $this->booking('2026-11-10', '2026-11-14');
        $this->booking('2026-11-11', '2026-11-14');

        $session = CleaningSession::factory()->create([
            'property_id' => $this->property->id,
            'owner_id' => $this->owner->id,
            'scheduled_date' => '2026-11-14',
            'booking_id' => null,
            'no_guest' => false,
        ]);

        CleaningFlowService::autoTag($session);

        $this->assertNull($session->fresh()->booking_id);
    }

    private function booking(string $checkIn, string $checkOut): Booking
    {
        return Booking::factory()->create([
            'property_id' => $this->property->id,
            'guest_name' => 'Test Guest',
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'status' => 'guest_approved',
            'cancelled_at' => null,
        ]);
    }
}
