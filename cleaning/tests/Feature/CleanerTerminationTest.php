<?php

namespace Tests\Feature;

use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanerTerminationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        $this->artisan('db:seed', ['--class' => 'SetupRolesAndPermissionsSeeder']);
    }

    public function test_admin_can_deactivate_cleaner_with_reason()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cleaner = User::factory()->create(['is_active' => true]);
        $cleaner->assignRole('housekeeper');

        $response = $this->actingAs($admin)->post(route('users.deactivate', $cleaner), [
            'termination_reason' => 'Left company on good terms.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Cleaner account deactivated successfully.');

        $cleaner->refresh();
        $this->assertFalse($cleaner->is_active);
        $this->assertEquals('Left company on good terms.', $cleaner->termination_reason);
        $this->assertEquals($admin->id, $cleaner->terminated_by);
        $this->assertNotNull($cleaner->terminated_at);
    }

    public function test_admin_can_reactivate_cleaner()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cleaner = User::factory()->create([
            'is_active' => false,
            'terminated_at' => now(),
            'terminated_by' => $admin->id,
            'termination_reason' => 'Temporary suspension.',
        ]);
        $cleaner->assignRole('housekeeper');

        $response = $this->actingAs($admin)->post(route('users.reactivate', $cleaner));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Cleaner account reactivated successfully.');

        $cleaner->refresh();
        $this->assertTrue($cleaner->is_active);
        $this->assertNull($cleaner->termination_reason);
        $this->assertNull($cleaner->terminated_by);
        $this->assertNull($cleaner->terminated_at);
    }

    public function test_company_role_receives_403_forbidden()
    {
        $company = User::factory()->create();
        $company->assignRole('company');

        $cleaner = User::factory()->create(['is_active' => true]);
        $cleaner->assignRole('housekeeper');

        $deactivateResponse = $this->actingAs($company)->post(route('users.deactivate', $cleaner), [
            'termination_reason' => 'Unauthorized attempt.',
        ]);
        $deactivateResponse->assertStatus(403);

        $cleaner->update(['is_active' => false]);

        $reactivateResponse = $this->actingAs($company)->post(route('users.reactivate', $cleaner));
        $reactivateResponse->assertStatus(403);
    }

    public function test_owner_role_receives_403_forbidden()
    {
        $owner = User::factory()->create();
        $owner->assignRole('owner');

        $cleaner = User::factory()->create(['is_active' => true]);
        $cleaner->assignRole('housekeeper');

        $deactivateResponse = $this->actingAs($owner)->post(route('users.deactivate', $cleaner), [
            'termination_reason' => 'Unauthorized attempt.',
        ]);
        $deactivateResponse->assertStatus(403);

        $cleaner->update(['is_active' => false]);

        $reactivateResponse = $this->actingAs($owner)->post(route('users.reactivate', $cleaner));
        $reactivateResponse->assertStatus(403);
    }

    public function test_housekeeper_role_receives_403_forbidden()
    {
        $cleaner1 = User::factory()->create(['is_active' => true]);
        $cleaner1->assignRole('housekeeper');

        $cleaner2 = User::factory()->create(['is_active' => true]);
        $cleaner2->assignRole('housekeeper');

        $deactivateResponse = $this->actingAs($cleaner1)->post(route('users.deactivate', $cleaner2), [
            'termination_reason' => 'Peer deactivation attempt.',
        ]);
        $deactivateResponse->assertStatus(403);

        $cleaner2->update(['is_active' => false]);

        $reactivateResponse = $this->actingAs($cleaner1)->post(route('users.reactivate', $cleaner2));
        $reactivateResponse->assertStatus(403);
    }

    public function test_admin_cannot_deactivate_admin_account()
    {
        $admin1 = User::factory()->create();
        $admin1->assignRole('admin');

        $admin2 = User::factory()->create(['is_active' => true]);
        $admin2->assignRole('admin');

        $response = $this->actingAs($admin1)->post(route('users.deactivate', $admin2), [
            'termination_reason' => 'Attempting to deactivate another admin.',
        ]);

        $response->assertStatus(403);
        $this->assertTrue($admin2->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_already_inactive_cleaner()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cleaner = User::factory()->create([
            'is_active' => false,
            'terminated_at' => now(),
            'termination_reason' => 'Already inactive',
        ]);
        $cleaner->assignRole('housekeeper');

        $response = $this->actingAs($admin)->post(route('users.deactivate', $cleaner), [
            'termination_reason' => 'Deactivating again',
        ]);

        $response->assertStatus(422);
    }

    public function test_deactivated_cleaner_cannot_login()
    {
        $cleaner = User::factory()->create([
            'email' => 'terminated@example.com',
            'password' => bcrypt('password123'),
            'is_active' => false,
        ]);
        $cleaner->assignRole('housekeeper');

        $response = $this->post('/login', [
            'email' => 'terminated@example.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email' => 'This cleaner account has been deactivated. Please contact your administrator.']);
        $this->assertGuest();
    }

    public function test_deactivated_cleaner_is_logged_out_by_middleware()
    {
        $cleaner = User::factory()->create(['is_active' => true]);
        $cleaner->assignRole('housekeeper');

        // Log in cleaner
        $this->actingAs($cleaner);

        // Soft-terminate cleaner while session active
        $cleaner->update(['is_active' => false]);

        // Attempt browsing authenticated route
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_cleaner_hidden_from_session_assignment_dropdown()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $activeCleaner = User::factory()->create(['name' => 'Active Cleaner', 'is_active' => true]);
        $activeCleaner->assignRole('housekeeper');

        $deactivatedCleaner = User::factory()->create(['name' => 'Terminated Cleaner', 'is_active' => false]);
        $deactivatedCleaner->assignRole('housekeeper');

        $response = $this->actingAs($admin)->get(route('manage.sessions.create'));

        $response->assertSee('Active Cleaner');
        $response->assertDontSee('Terminated Cleaner');
    }

    public function test_historical_sessions_and_reports_preserved_when_cleaner_deactivated()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cleaner = User::factory()->create(['is_active' => true]);
        $cleaner->assignRole('housekeeper');

        $property = Property::factory()->create(['owner_id' => $admin->id]);

        $session = CleaningSession::factory()->create([
            'property_id' => $property->id,
            'housekeeper_id' => $cleaner->id,
            'status' => 'completed',
        ]);

        // Deactivate cleaner
        $this->actingAs($admin)->post(route('users.deactivate', $cleaner), [
            'termination_reason' => 'Deactivating with history.',
        ]);

        // Verify session remains intact with cleaner relationship
        $this->assertDatabaseHas('cleaning_sessions', [
            'id' => $session->id,
            'housekeeper_id' => $cleaner->id,
        ]);
        $this->assertEquals($cleaner->id, $session->fresh()->housekeeper_id);
    }

    public function test_readding_deactivated_cleaner_email_fails_with_specific_validation_message()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cleaner = User::factory()->create([
            'email' => 'cleaner@example.com',
            'is_active' => false,
        ]);
        $cleaner->assignRole('housekeeper');

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'New Cleaner Account',
            'email' => 'cleaner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'housekeeper',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This cleaner already exists but has been terminated. Reactivate the existing account instead of creating a new one.',
        ]);
    }

    public function test_readding_email_handles_whitespace_and_case_insensitivity()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $cleaner = User::factory()->create([
            'email' => 'john.doe@example.com',
            'is_active' => false,
        ]);
        $cleaner->assignRole('housekeeper');

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'John Doe Duplicate',
            'email' => '  JOHN.DOE@EXAMPLE.COM  ',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'housekeeper',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This cleaner already exists but has been terminated. Reactivate the existing account instead of creating a new one.',
        ]);
    }
}
