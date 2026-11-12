<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BookingShowEarlyCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_early_checkout_shows_manual_checkin_action(): void
    {
        $booking = $this->createApprovedBooking([
            'check_out_date' => now()->addDays(2)->toDateString(),
            'checked_out_at' => now()->subDay(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.guests.show', $booking))
            ->assertOk()
            ->assertSee('Manually Mark Checked In');
    }

    public function test_checkout_on_or_after_scheduled_date_does_not_show_manual_checkin_action(): void
    {
        $booking = $this->createApprovedBooking([
            'check_out_date' => now()->toDateString(),
            'checked_out_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.guests.show', $booking))
            ->assertOk()
            ->assertDontSee('Manually Mark Checked In');
    }

    private function createApprovedBooking(array $attributes): Booking
    {
        return Booking::factory()->create(array_merge([
            'property_id' => Property::factory(),
            'checkin_approved_at' => now()->subDays(2),
        ], $attributes));
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }
}
