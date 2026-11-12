<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingParkingAdminEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_parking_from_guest_details_editor(): void
    {
        $property = Property::factory()->create([
            'parking_rate_sunday' => 5,
            'parking_rate_monday' => 5,
            'parking_rate_tuesday' => 5,
            'parking_rate_wednesday' => 5,
            'parking_rate_thursday' => 5,
            'parking_rate_friday' => 5,
            'parking_rate_saturday' => 5,
        ]);
        $checkIn = now()->addDays(1)->toDateString();
        $checkOut = now()->addDays(3)->toDateString();
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'parking_needed' => false,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'reservation_id' => 'PARKING-EDIT-1',
            'id_type' => 'state_id',
        ]);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->put(route('admin.guests.update', $booking), [
                'reservation_id' => $booking->reservation_id,
                'guest_name' => $booking->guest_name,
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'property_id' => $property->id,
                'id_type' => 'state_id',
                'status' => 'pending',
                'parking_needed' => '1',
            ])
            ->assertRedirect(route('admin.guests.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'parking_needed' => true,
            'parking_charge' => 10,
        ]);
    }
}
