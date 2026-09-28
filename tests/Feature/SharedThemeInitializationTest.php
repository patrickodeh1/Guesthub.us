<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SharedThemeInitializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_renders_the_shared_theme_initializer(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('data-theme-init', false)
            ->assertSee('root.style.colorScheme', false);
    }

    public function test_admin_shell_includes_a_theme_toggle(): void
    {
        $this->assertStringContainsString(
            'id="theme-toggle"',
            File::get(resource_path('views/layouts/unified.blade.php')),
        );
    }

    public function test_layout_views_do_not_use_os_preference_for_the_theme(): void
    {
        $shells = [
            resource_path('views/layouts/unified.blade.php'),
            resource_path('views/layouts/guest.blade.php'),
            resource_path('views/layouts/cleaning-guest.blade.php'),
            resource_path('views/auth/login.blade.php'),
        ];

        foreach ($shells as $shell) {
            $this->assertStringContainsString(
                "layouts.partials.theme-init",
                File::get($shell),
                $shell,
            );
        }

        foreach (File::allFiles(resource_path('views/layouts')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $this->assertStringNotContainsString(
                'prefers-color-scheme',
                $file->getContents(),
                $file->getRelativePathname(),
            );
        }
    }

    public function test_brand_variables_follow_extreme_theme_colors_on_authenticated_shells(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'status' => 'active',
            'password' => Hash::make('StrongPassword123!'),
        ]);
        $admin->assignRole('admin');

        Setting::putValue('theme_color', '#facc15');
        Setting::putValue('button_primary_color', '#facc15');

        $light = $this->actingAs($admin)->get(route('profile.edit'));
        $light->assertOk()
            ->assertSee('--theme-primary: #facc15', false)
            ->assertSee('--on-primary: #0f172a', false);

        Setting::putValue('theme_color', '#111827');
        Setting::putValue('button_primary_color', '#111827');

        $dark = $this->actingAs($admin)->get(route('profile.edit'));
        $dark->assertOk()
            ->assertSee('--theme-primary: #111827', false)
            ->assertSee('--on-primary: #ffffff', false);

        $this->assertSame('#0f172a', Branding::contrastText('#facc15'));
        $this->assertSame('#ffffff', Branding::contrastText('#111827'));
    }

    public function test_admin_settings_keeps_brand_color_in_sync_with_theme_color(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'status' => 'active',
            'password' => Hash::make('StrongPassword123!'),
        ]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->put('/admin/settings', [
            'gps_radius_meters' => 150,
            'theme_color' => '#facc15',
            'site_name' => 'GuestHub',
        ]);

        $response->assertRedirect();
        $this->assertSame('#facc15', Setting::getValue('theme_color'));
        $this->assertSame('#facc15', Setting::getValue('brand_color'));
    }

    public function test_admin_can_reset_brand_and_button_colors_to_defaults(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'status' => 'active',
            'password' => Hash::make('StrongPassword123!'),
        ]);
        $admin->assignRole('admin');

        Setting::putValue('theme_color', '#facc15');
        Setting::putValue('brand_color', '#facc15');
        Setting::putValue('button_primary_color', '#111827');

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.reset-colors'));
        $response->assertRedirect();

        $this->assertSame(\App\Support\Branding::DEFAULT_THEME_COLOR, Setting::getValue('theme_color'));
        $this->assertSame(\App\Support\Branding::DEFAULT_THEME_COLOR, Setting::getValue('brand_color'));
        $this->assertSame(\App\Support\Branding::DEFAULT_BUTTON_COLOR, Setting::getValue('button_primary_color'));
    }
}
