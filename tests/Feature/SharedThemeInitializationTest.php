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

    public function test_login_uses_the_shared_light_theme_initializer(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('color-scheme', false)
            ->assertSee("root.classList.remove('dark')", false);
    }

    public function test_admin_shell_keeps_the_product_light_only_without_a_theme_toggle(): void
    {
        $layout = File::get(resource_path('views/layouts/unified.blade.php'));

        $this->assertStringContainsString("layouts.partials.theme-init", $layout);
        $this->assertStringNotContainsString('id="theme-toggle"', $layout);
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

    public function test_brand_variables_keep_the_fixed_product_palette_despite_legacy_settings(): void
    {
        Setting::putValue('theme_color', '#facc15');
        Setting::putValue('brand_color', '#facc15');
        Setting::putValue('button_primary_color', '#111827');

        $this->assertSame(Branding::DEFAULT_THEME_COLOR, Branding::themeColor());
        $this->assertSame(Branding::DEFAULT_BUTTON_COLOR, Branding::buttonColor());
        $this->assertSame('#0f172a', Branding::contrastText('#facc15'));
        $this->assertSame('#ffffff', Branding::contrastText('#111827'));
    }

    public function test_admin_settings_updates_do_not_change_the_fixed_brand_palette(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'status' => 'active',
            'password' => Hash::make('StrongPassword123!'),
        ]);
        $admin->assignRole('admin');

        $this->actingAs($admin)->put('/admin/settings', [
            'gps_radius_meters' => 150,
            'theme_color' => '#facc15',
            'button_primary_color' => '#111827',
            'site_name' => 'GuestHub',
        ])->assertRedirect();

        $this->assertSame(Branding::DEFAULT_THEME_COLOR, Branding::themeColor());
        $this->assertSame(Branding::DEFAULT_BUTTON_COLOR, Branding::buttonColor());
    }

    public function test_legacy_color_settings_cannot_override_the_shared_branding_helpers(): void
    {
        Setting::putValue('theme_color', '#ffffff');
        Setting::putValue('brand_color', '#000000');
        Setting::putValue('button_primary_color', '#ff0000');

        $this->assertSame(Branding::DEFAULT_THEME_COLOR, Branding::themeColor());
        $this->assertSame(Branding::DEFAULT_BUTTON_COLOR, Branding::buttonColor());
    }
}
