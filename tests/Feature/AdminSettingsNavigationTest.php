<?php

namespace Tests\Feature;

use App\Models\User;
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
        ]);
        $admin->assignRole($adminRole);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.settings.edit'), false);
    }
}
