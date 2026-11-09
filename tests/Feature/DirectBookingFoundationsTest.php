<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Feature;
use App\Models\HostPaymentMethod;
use App\Models\Property;
use App\Models\PropertyListing;
use App\Models\User;
use App\Services\BookingConflictService;
use App\Services\CleaningJobService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DirectBookingFoundationsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner = User::factory()->create(['must_change_password' => false]);
        $owner->assignRole('owner');

        return $owner;
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_owner_can_save_and_publish_a_valid_listing_with_resized_photos_and_cents(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'latitude' => '32.7157360',
            'longitude' => '-117.1610870',
            'beds' => 2,
            'baths' => 1,
        ]);
        Feature::where('key', 'direct_booking_site')->update(['active' => true]);
        Feature::where('key', 'manual_payments')->update(['active' => true]);

        $response = $this->actingAs($owner)->put(route('admin.properties.listing.save', $property), [
            'published' => '1',
            'booking_mode' => 'instant',
            'advance_notice_hours' => '24',
            'min_nights' => '1',
            'cancellation_policy' => 'moderate',
            'max_guests' => '4',
            'base_rate' => '123.45',
            'cleaning_fee' => '25.00',
            'payment_methods' => [
                [
                    'type' => 'venmo',
                    'label' => 'Pay by Venmo',
                    'handle' => '@host',
                    'instructions' => 'Use the booking reference.',
                    'enabled' => '1',
                ],
            ],
            'photos' => array_map(
                fn ($index) => UploadedFile::fake()->image("photo-{$index}.jpg", 800, 600),
                range(1, 5),
            ),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $listing = PropertyListing::where('property_id', $property->id)->firstOrFail();
        $this->assertNotNull($listing->published_at);
        $this->assertSame(12345, $listing->base_rate_cents);
        $this->assertSame(2500, $listing->cleaning_fee_cents);
        $this->assertSame(5, $listing->photos()->count());
        $this->assertStringEndsWith('.webp', $listing->photos()->first()->path);
        $this->assertSame(1, HostPaymentMethod::where('owner_id', $owner->id)->count());
    }

    public function test_incomplete_listing_cannot_be_published_but_can_be_saved_as_draft(): void
    {
        $owner = $this->owner();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $payload = [
            'published' => '1',
            'booking_mode' => 'instant',
            'advance_notice_hours' => '24',
            'min_nights' => '1',
            'cancellation_policy' => 'moderate',
            'max_guests' => '2',
            'base_rate' => '100.00',
            'public_description' => 'A deliberately short draft description.',
        ];

        $this->actingAs($owner)
            ->from(route('admin.properties.listing.edit', $property))
            ->put(route('admin.properties.listing.save', $property), $payload)
            ->assertRedirect(route('admin.properties.listing.edit', $property))
            ->assertSessionHasErrors(['public_description', 'photos', 'coordinates', 'payment_methods']);

        $this->assertDatabaseMissing('property_listings', ['property_id' => $property->id]);
    }

    public function test_pending_payment_blocks_conflicting_dates_without_creating_cleaning_or_guest_portal_access(): void
    {
        $owner = $this->owner();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'status' => Booking::STATUS_PENDING_PAYMENT,
            'check_in_date' => now()->addDays(3)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->assertDatabaseCount('cleaning_sessions', 0);
        $this->assertSame(
            $booking->id,
            BookingConflictService::find(
                $property->id,
                now()->addDays(4)->toDateString(),
                now()->addDays(6)->toDateString(),
            )?->id,
        );

        $this->get(route('guest.show', [$booking->booking_id, $booking->token]))->assertNotFound();
    }

    public function test_pending_payment_cannot_be_manually_assigned_a_cleaning_job(): void
    {
        $owner = $this->owner();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'status' => Booking::STATUS_PENDING_PAYMENT,
        ]);

        try {
            app(CleaningJobService::class)->create($owner, [
                'property_id' => $property->id,
                'booking_id' => $booking->id,
                'housekeeper_id' => 999,
                'scheduled_date' => now()->addDays(2)->toDateString(),
            ]);
            $this->fail('A pending-payment booking should not get a cleaning assignment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('booking_id', $exception->errors());
        }

        $this->assertDatabaseCount('cleaning_sessions', 0);
    }

    public function test_admin_can_change_feature_price_and_free_status(): void
    {
        $admin = $this->admin();
        $feature = Feature::where('key', 'direct_booking_site')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.features.update', $feature), [
                'is_free' => '0',
                'active' => '1',
                'price_cents' => '1200',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('features', [
            'id' => $feature->id,
            'is_free' => false,
            'price_cents' => 1200,
        ]);
    }

    public function test_pending_payment_is_not_included_in_registration_reminders(): void
    {
        $owner = $this->owner();
        $property = Property::factory()->create(['owner_id' => $owner->id]);
        $booking = Booking::factory()->create([
            'property_id' => $property->id,
            'status' => Booking::STATUS_PENDING_PAYMENT,
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(4)->toDateString(),
            'last_registration_reminder_at' => null,
        ]);

        $this->artisan('bookings:send-registration-reminders')->assertSuccessful();

        $this->assertNull($booking->fresh()->last_registration_reminder_at);
    }
}
