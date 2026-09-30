<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminSettingsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_cleaning_dashboard_with_guest_portal_settings_link(): void
    {
        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
            'status' => 'active',
        ]);
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.settings.edit'), false)
            ->assertSee('Today')
            ->assertSee('Upcoming Sessions (7d)');

        $notificationsResponse = $this->get(route('admin.settings.notifications.edit'));
        $notificationsResponse->assertOk()
            ->assertSee(route('admin.properties.index'), false)
            ->assertSee('Global settings control guest lifecycle messages');
    }

    public function test_housekeeper_dashboard_renders_cleaning_panels_without_guest_portal_panels(): void
    {
        $housekeeper = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
            'status' => 'active',
        ]);
        $housekeeper->assignRole(Role::firstOrCreate(['name' => 'housekeeper', 'guard_name' => 'web']));

        $response = $this->actingAs($housekeeper)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Upcoming Sessions (7d)')
            ->assertSee('My Assignments')
            ->assertDontSee('Smart Locks')
            ->assertDontSee('Needs Attention');
    }

    public function test_admin_can_load_the_consolidated_settings_sections(): void
    {
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
            'status' => 'active',
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $response = $this->actingAs($admin)->get(route('admin.settings.edit'));

        $response->assertOk()
            ->assertSee('Cleaning Ops')
            ->assertSee('Reports');
    }

    public function test_admin_can_save_cleaning_report_and_legal_settings_from_one_form(): void
    {
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
            'status' => 'active',
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']));

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'gps_radius_meters' => 225,
            'timezone' => 'America/New_York',
            'auto_save_enabled' => 1,
            'auto_save_delay' => 800,
            'mandatory_instruction_viewing' => 1,
            'global_required_instruction_views' => 4,
            'report_header_color' => '#123456',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('225', (string) Setting::getValue('gps_radius_meters'));
        $this->assertSame('#123456', Setting::getValue('report_header_color'));
    }

    public function test_non_admin_cannot_open_consolidated_settings(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']));

        $this->actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
    }
}
