<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRoles(array $roles): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'status' => 'active',
        ]);

        foreach ($roles as $role) {
            $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        }

        return $user;
    }

    private function itemsFor(User $user): array
    {
        return collect(app(Navigation::class)->forUser($user))
            ->flatMap(fn (array $section) => $section['items'])
            ->keyBy('label')
            ->all();
    }

    public function test_admin_navigation_uses_current_primary_and_settings_groups(): void
    {
        $items = $this->itemsFor($this->userWithRoles(['admin']));

        $this->assertArrayHasKey('Dashboard', $items);
        $this->assertArrayHasKey('Guests', $items);
        $this->assertArrayHasKey('Properties', $items);
        $this->assertArrayHasKey('Jobs', $items);
        $this->assertArrayHasKey('Media', $items);
        $this->assertArrayHasKey('Administration', $items);
        $this->assertArrayHasKey('Settings', $items);

        $this->assertSame(route('manage.sessions.index'), $items['Jobs']['href']);
        $this->assertSame(
            ['Media Library', 'Video Library', 'Task Photos', 'Categories'],
            collect($items['Media']['children'])->pluck('label')->all()
        );
        $settingsLabels = collect($items['Settings']['children'])->pluck('label')->all();
        $this->assertContains('Inventory', $settingsLabels);
        $this->assertContains('Guest Notices', $settingsLabels);
        $this->assertContains('Cleaning Rooms', $settingsLabels);
        $this->assertContains('Cleaning Tasks', $settingsLabels);
    }

    public function test_manager_can_access_operational_navigation_but_not_admin_only_settings(): void
    {
        $items = $this->itemsFor($this->userWithRoles(['manager']));

        $this->assertArrayHasKey('Jobs', $items);
        $this->assertArrayHasKey('Media', $items);
        $this->assertArrayHasKey('Administration', $items);
        $this->assertArrayNotHasKey('Settings', $items);
        $this->assertArrayNotHasKey('Users', $items);
    }

    public function test_owner_navigation_includes_property_operations_and_plan(): void
    {
        $items = $this->itemsFor($this->userWithRoles(['owner']));

        $this->assertArrayHasKey('Properties', $items);
        $this->assertArrayHasKey('Jobs', $items);
        $this->assertArrayHasKey('Media', $items);
        $this->assertArrayHasKey('Administration', $items);
        $this->assertArrayHasKey('Settings', $items);
        $this->assertContains('Plan', collect($items['Settings']['children'])->pluck('label')->all());
    }

    public function test_housekeeper_gets_cleaner_navigation_without_admin_links(): void
    {
        $items = $this->itemsFor($this->userWithRoles(['housekeeper']));

        $this->assertArrayHasKey('Calendar', $items);
        $this->assertArrayNotHasKey('Guests', $items);
        $this->assertArrayNotHasKey('Properties', $items);
        $this->assertArrayNotHasKey('Jobs', $items);
        $this->assertArrayNotHasKey('Media', $items);
        $this->assertArrayNotHasKey('Administration', $items);

        $housekeeper = $this->userWithRoles(['housekeeper']);
        $sections = collect(app(Navigation::class)->forUser($housekeeper));
        $work = $sections->firstWhere('label', 'My Work');
        $this->assertSame(['Dashboard', 'My Jobs', 'Training', 'My Account'], collect($work['items'])->pluck('label')->all());
    }

    public function test_admin_with_housekeeper_role_does_not_receive_housekeeper_only_menu(): void
    {
        $admin = $this->userWithRoles(['admin', 'housekeeper']);
        $items = $this->itemsFor($admin);

        $this->assertArrayHasKey('Jobs', $items);
        $work = collect(app(Navigation::class)->forUser($admin))->firstWhere('label', 'My Work');
        $this->assertSame([], $work['items']);
    }
}
