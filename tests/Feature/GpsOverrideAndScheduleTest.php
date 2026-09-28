<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use App\Models\CleaningSession;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GpsOverrideAndScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles exist
        $this->artisan('db:seed', ['--class' => 'SetupRolesAndPermissionsSeeder']);
    }

    public function test_cleaner_cannot_start_session_when_too_far()
    {
        $cleaner = User::factory()->create();
        $cleaner->assignRole('housekeeper');
        
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'geo_radius_m' => 100,
        ]);

        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'pending',
            'scheduled_date' => now()->toDateString(),
        ]);

        // Coordinates very far away from New York
        $response = $this->actingAs($cleaner)->post(route('sessions.start', $session), [
            'latitude' => 34.0522,
            'longitude' => -118.2437,
        ]);

        $response->assertSessionHasErrors(['gps']);
        $this->assertEquals('pending', $session->fresh()->status);
    }

    public function test_admin_can_grant_gps_override()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $cleaner = User::factory()->create();
        $cleaner->assignRole('housekeeper');

        $property = Property::factory()->create([
            'owner_id' => $admin->id
        ]);

        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $admin->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'pending',
        ]);

        $reason = "Cleaner's GPS is malfunctioning";

        $response = $this->actingAs($admin)->post(route('sessions.gps-override', $session), [
            'reason' => $reason,
        ]);

        $response->assertSessionHas('success');
        $session->refresh();

        $this->assertTrue($session->gps_override_enabled);
        $this->assertEquals($admin->id, $session->gps_override_approved_by);
        $this->assertEquals($reason, $session->gps_override_reason);
        $this->assertNotNull($session->gps_override_timestamp);
    }

    public function test_cleaner_cannot_grant_gps_override()
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $cleaner = User::factory()->create();
        $cleaner->assignRole('housekeeper');

        $property = Property::factory()->create([
            'owner_id' => $owner->id
        ]);

        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($cleaner)->post(route('sessions.gps-override', $session), [
            'reason' => "Trying to bypass",
        ]);

        $response->assertForbidden();
        $this->assertFalse($session->fresh()->gps_override_enabled);
    }

    public function test_cleaner_can_start_session_when_too_far_if_override_granted()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cleaner = User::factory()->create();
        $cleaner->assignRole('housekeeper');
        
        $property = Property::factory()->create([
            'owner_id' => $admin->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'geo_radius_m' => 100,
        ]);

        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $admin->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'pending',
            'scheduled_date' => now()->toDateString(),
            'gps_override_enabled' => true,
            'gps_override_approved_by' => $admin->id,
            'gps_override_reason' => 'Approved',
            'gps_override_timestamp' => now(),
        ]);

        // Coordinates very far away
        $response = $this->actingAs($cleaner)->post(route('sessions.start', $session), [
            'latitude' => 34.0522,
            'longitude' => -118.2437,
        ]);

        $response->assertRedirect();
        $this->assertEquals('in_progress', $session->fresh()->status);
        $this->assertNull($session->fresh()->gps_confirmed_at);
    }

    public function test_cleaner_cannot_access_checklist_before_scheduled_time()
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $cleaner = User::factory()->create();
        $cleaner->assignRole('housekeeper');
        
        $property = Property::factory()->create([
            'owner_id' => $owner->id
        ]);
        
        $propertyTz = config('app.timezone');
        $futureTime = now($propertyTz)->addHours(2);

        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'pending',
            'scheduled_date' => $futureTime->toDateString(),
            'scheduled_time' => $futureTime->toTimeString(),
        ]);

        $response = $this->actingAs($cleaner)->get(route('sessions.show', $session));

        $response->assertOk();
        $response->assertSee('Session Not Yet Available');
        $response->assertSee('This cleaning is not yet available. Access will be granted at the scheduled start time');
        
        // Assert we do not see the View Only mode (which shows they can view it)
        $response->assertDontSee('You can view the checklist, but you can only start working');
    }

    public function test_cleaner_can_access_checklist_after_scheduled_time()
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $cleaner = User::factory()->create();
        $cleaner->assignRole('housekeeper');
        
        $property = Property::factory()->create([
            'owner_id' => $owner->id
        ]);
        
        $propertyTz = config('app.timezone');
        $pastTime = now($propertyTz)->subHours(1);

        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'pending',
            'scheduled_date' => $pastTime->toDateString(),
            'scheduled_time' => $pastTime->toTimeString(),
        ]);

        $response = $this->actingAs($cleaner)->get(route('sessions.show', $session));

        $response->assertOk();
        $response->assertDontSee('Session Not Yet Available');
        $response->assertSee('Ready to Start');
    }

    public function test_admin_can_access_checklist_before_scheduled_time()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        
        $property = Property::factory()->create([
            'owner_id' => $admin->id
        ]);
        
        $propertyTz = config('app.timezone');
        $futureTime = now($propertyTz)->addHours(2);

        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $admin->id,
            'status' => 'pending',
            'scheduled_date' => $futureTime->toDateString(),
            'scheduled_time' => $futureTime->toTimeString(),
        ]);

        $response = $this->actingAs($admin)->get(route('sessions.show', $session));

        $response->assertOk();
        $response->assertDontSee('Session Not Yet Available');
        // Because admin is not a housekeeper, the view logic shows the standard Start form or the checklist depending on how admins see it.
        // But importantly they don't get the 'too early' block.
    }
}
