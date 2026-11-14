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

    private function createUser(): User
    {
        return User::factory()->create([
            'email_verified_at' => now(),
            'status' => 'active',
            'must_change_password' => false,
            'password' => bcrypt('StrongPassword123!'),
        ]);
    }

    public function test_cleaner_cannot_verify_location_when_too_far(): void
    {
        $cleaner = $this->createUser();
        $cleaner->assignRole('housekeeper');

        $owner = $this->createUser();
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
            'gps_confirmed_at' => null,
            'scheduled_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($cleaner)->postJson(route('sessions.verify-location', $session), [
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertNull($session->fresh()->gps_confirmed_at);
        $this->assertSame('pending', $session->fresh()->status);
    }

    public function test_admin_can_grant_gps_override()
    {
        $admin = $this->createUser();
        $admin->assignRole('admin');
        
        $cleaner = $this->createUser();
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
        $owner = $this->createUser();
        $owner->assignRole('owner');

        $cleaner = $this->createUser();
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
        $admin = $this->createUser();
        $admin->assignRole('admin');

        $cleaner = $this->createUser();
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
        $owner = $this->createUser();
        $owner->assignRole('owner');

        $cleaner = $this->createUser();
        $cleaner->assignRole('housekeeper');
        
        $property = Property::factory()->create([
            'owner_id' => $owner->id
        ]);
        
        $propertyTz = config('app.timezone');
        $futureTime = now($propertyTz)->addHours(4);

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
        $response->assertSee('You cannot start yet.');
        $response->assertDontSee('Ready to start');
        
        // Assert we do not see the View Only mode (which shows they can view it)
        $response->assertDontSee('You can view the checklist, but you can only start working');
    }

    public function test_cleaner_can_access_checklist_after_scheduled_time()
    {
        $owner = $this->createUser();
        $owner->assignRole('owner');

        $cleaner = $this->createUser();
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
        $response->assertSee('Ready to start');
    }

    public function test_owner_can_open_cleaner_workflow_without_changing_assigned_cleaner(): void
    {
        $owner = $this->createUser();
        $owner->assignRole('owner');
        $cleaner = $this->createUser();
        $cleaner->assignRole('housekeeper');

        $property = Property::factory()->create([
            'owner_id' => $owner->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);
        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'pending',
            'scheduled_date' => now()->toDateString(),
            'scheduled_time' => now()->format('H:i:s'),
            'gps_confirmed_at' => null,
        ]);

        $response = $this->actingAs($owner)->get(route('sessions.show', [
            'session' => $session->id,
            'view_as_cleaner' => 1,
        ]));

        $response->assertOk();
        $response->assertSee('Verify my location');
        $response->assertDontSee('Begin (preview only)');
        $this->assertSame($cleaner->id, $session->fresh()->housekeeper_id);
    }

    public function test_owner_acting_as_cleaner_must_verify_gps_before_starting(): void
    {
        $owner = $this->createUser();
        $owner->assignRole('owner');
        $cleaner = $this->createUser();
        $cleaner->assignRole('housekeeper');

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
            'scheduled_time' => now()->format('H:i:s'),
            'gps_confirmed_at' => null,
        ]);

        $verifyUrl = route('sessions.verify-location', [
            'session' => $session->id,
            'view_as_cleaner' => 1,
        ]);

        $startUrl = route('sessions.start', [
            'session' => $session->id,
            'view_as_cleaner' => 1,
        ]);
        $withoutGps = $this->actingAs($owner)->post($startUrl, [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);
        $withoutGps->assertRedirect()->assertSessionHasErrors('gps');
        $this->assertSame('pending', $session->fresh()->status);

        $tooFar = $this->postJson($verifyUrl, [
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'accuracy' => 10,
        ]);
        $tooFar->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertNull($session->fresh()->gps_confirmed_at);

        $verified = $this->postJson($verifyUrl, [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'accuracy' => 10,
        ]);
        $verified->assertOk()->assertJsonPath('ok', true);
        $this->assertNotNull($session->fresh()->gps_confirmed_at);

        $started = $this->post($startUrl, [
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);
        $started->assertRedirect();
        $this->assertSame('in_progress', $session->fresh()->status);
        $this->assertSame($cleaner->id, $session->fresh()->housekeeper_id);
    }

    public function test_owner_acting_as_cleaner_cannot_use_an_existing_gps_override(): void
    {
        $owner = $this->createUser();
        $owner->assignRole('owner');
        $cleaner = $this->createUser();
        $cleaner->assignRole('housekeeper');

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
            'gps_override_enabled' => true,
            'gps_confirmed_at' => null,
        ]);

        $response = $this->actingAs($owner)->postJson(route('sessions.verify-location', [
            'session' => $session->id,
            'view_as_cleaner' => 1,
        ]), [
            'latitude' => 34.0522,
            'longitude' => -118.2437,
            'accuracy' => 10,
        ]);

        $response->assertStatus(422)->assertJsonPath('ok', false);
        $this->assertNull($session->fresh()->gps_confirmed_at);
        $this->assertSame($cleaner->id, $session->fresh()->housekeeper_id);
    }

    public function test_admin_can_access_checklist_before_scheduled_time()
    {
        $admin = $this->createUser();
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
