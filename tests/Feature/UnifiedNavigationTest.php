<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_jobs_and_media_collapsibles_contain_their_cleaning_links(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
        ]);
        $admin->assignRole('admin');

        $sections = app(Navigation::class)->forUser($admin);
        $items = collect($sections)->flatMap(fn (array $section) => $section['items'])->keyBy('label');
        $jobs = $items->get('Jobs');
        $media = $items->get('Media');
        $settings = $items->get('Settings');

        $this->assertTrue($jobs['collapsible']);
        $this->assertSame(route('manage.sessions.index'), $jobs['href']);
        $this->assertSame(
            ['Cleaning Rooms', 'Cleaning Tasks'],
            collect($jobs['children'])->pluck('label')->all()
        );
        $this->assertSame(route('rooms.index'), $jobs['children'][0]['href']);
        $this->assertSame(route('tasks.index'), $jobs['children'][1]['href']);

        $this->assertSame(
            ['Media Library', 'Video Library', 'Categories'],
            collect($media['children'])->pluck('label')->all()
        );
        $this->assertSame(route('admin.videos.index'), $media['children'][1]['href']);
        $this->assertNotContains(
            'Video Library',
            collect($settings['children'])->pluck('label')->all()
        );
        $this->assertNotContains(
            'Cleaning Rooms',
            collect($settings['children'])->pluck('label')->all()
        );
        $this->assertNotContains(
            'Cleaning Tasks',
            collect($settings['children'])->pluck('label')->all()
        );
    }

    public function test_sidebar_links_follow_existing_visibility_for_all_seven_roles(): void
    {
        $portalRoles = ['admin', 'owner', 'company'];
        foreach (['admin', 'manager', 'owner', 'company', 'housekeeper', 'staff', 'viewer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

            $user = User::factory()->create([
                'email_verified_at' => now(),
                'must_change_password' => false,
                'password' => Hash::make('StrongPassword123!'),
            ]);
            $user->assignRole($role);

            $response = $this->actingAs($user)->get(route('profile.edit'));
            $response->assertOk();

            foreach (['admin.dashboard', 'admin.guests.index'] as $routeName) {
                $this->assertNavigationLink($response, route($routeName), in_array($role, $portalRoles, true));
            }

            if (! in_array($role, $portalRoles, true)) {
                $this->get(route('admin.dashboard'))->assertForbidden();
            }

            foreach (['assignments.index', 'sessions.index', 'training.index', 'resources.photos'] as $routeName) {
                $this->assertNavigationLink(
                    $response,
                    route($routeName),
                    $role === 'housekeeper'
                );
            }

            $this->assertNavigationLink(
                $response,
                route('admin.settings.edit'),
                $role === 'admin'
            );

            foreach (['admin.properties.index', 'rooms.index', 'tasks.index'] as $routeName) {
                $this->assertNavigationLink(
                    $response,
                    route($routeName),
                    in_array($role, ['admin', 'owner', 'company'], true)
                );
            }

            $this->assertNavigationLink($response, route('admin.users.index'), $role === 'admin');
            $this->assertNavigationLink($response, route('admin.early-access-leads.index'), $role === 'admin');
            $this->assertNavigationLink(
                $response,
                route('admin.logs.index'),
                in_array($role, ['admin', 'manager', 'owner'], true)
            );
            $this->assertNavigationLink(
                $response,
                route('activity.index'),
                false
            );

            if ($role === 'housekeeper') {
                preg_match('/<nav\\b[^>]*data-tour="sidebar-nav"[^>]*>(.*?)<\\/nav>/s', $response->getContent(), $navigation);
                $this->assertStringNotContainsString('<details', $navigation[1] ?? '');
                $this->assertStringNotContainsString('/admin/', $navigation[1] ?? '');
                $this->assertNavigationLink($response, route('calendar.index'), true);
                $this->assertNavigationLink($response, route('resources.videos'), true);
                $this->assertNavigationLink($response, route('resources.guides'), true);
                $this->assertNavigationLink($response, route('dashboard'), false);
            }
        }
    }

    public function test_owner_availability_links_only_include_owned_properties(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $owner = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
        ]);
        $owner->assignRole('owner');

        $otherOwner = User::factory()->create();
        $ownedProperty = Property::factory()->create([
            'name' => 'Owned Property',
            'owner_id' => $owner->id,
        ]);
        $otherProperty = Property::factory()->create([
            'name' => 'Other Property',
            'owner_id' => $otherOwner->id,
        ]);

        $response = $this->actingAs($owner)->get(route('profile.edit'));

        $response->assertOk();
        $this->assertNavigationLink(
            $response,
            route('admin.properties.availability.index', $ownedProperty),
            true
        );
        $this->assertNavigationLink(
            $response,
            route('admin.properties.availability.index', $otherProperty),
            false
        );
        $this->assertNavigationLink(
            $response,
            route('admin.instructions.show', $ownedProperty),
            true
        );
        $this->assertNavigationLink(
            $response,
            route('admin.guest-guide.show', $ownedProperty),
            true
        );
    }

    public function test_admin_with_a_cleaner_role_sees_training_but_not_housekeeper_only_navigation(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'housekeeper', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
            'status' => 'active',
        ]);
        $admin->assignRole(['admin', 'housekeeper']);

        $response = $this->actingAs($admin)->get(route('admin.guide'));

        $response->assertOk();
        $response->assertSee('Cleaning');
        $response->assertSee(route('admin.videos.index'))
            ->assertSee(route('training.index'));
        foreach (['My Jobs', 'Calendar', 'Sessions', 'Photos', 'Guides'] as $label) {
            $this->assertStringNotContainsString('>'.$label.'<', $this->sidebarHtml($response));
        }
    }

    public function test_root_admin_page_keeps_the_guest_admin_order_and_contains_cleaning_links(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
            'status' => 'active',
        ]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.guide'));

        $response->assertOk();
        $response->assertSee('Guest Admin');
        $response->assertSee('Settings');
        $response->assertSee('Cleaning Ops');
        $response->assertSee('Administration');
        $response->assertSee('Cleaning Dashboard');
        $response->assertSee(route('manage.sessions.create'));
        $response->assertDontSee(route('resources.photos'));
        $response->assertSee('data-tour="topbar"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="theme-toggle"'));
        $this->assertSame(1, substr_count($response->getContent(), 'id="admin-sidebar-open"'));

        preg_match('/<nav\b[^>]*data-tour="sidebar-nav"[^>]*>(.*?)<\/nav>/s', $response->getContent(), $navigation);
        preg_match_all('/<p\b[^>]*>(.*?)<\/p>/s', $navigation[1] ?? '', $sectionMatches);
        $sections = array_map(
            fn (string $label) => trim(strip_tags($label)),
            $sectionMatches[1] ?? []
        );

        $this->assertSame(['Guest Admin', 'Settings', 'Cleaning Ops', 'Account', 'Administration'], $sections);

        $navContent = $navigation[1] ?? '';
        $rootLinks = [
            route('admin.dashboard'),
            route('admin.guests.index'),
            route('admin.properties.index'),
            route('admin.settings.edit'),
            route('admin.categories.index'),
        ];
        $positions = array_map(fn (string $url) => strpos($navContent, $url), $rootLinks);
        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());

        preg_match('/<details\b[^>]*>.*?data-tour="nav-cleaning".*?<\/details>/s', $navContent, $cleaningGroup);
        $this->assertNotEmpty($cleaningGroup);
        $this->assertStringContainsString(route('dashboard'), $cleaningGroup[0]);
        $this->assertStringNotContainsString(route('assignments.index'), $cleaningGroup[0]);
        $this->assertStringNotContainsString(route('sessions.index'), $cleaningGroup[0]);
        $this->assertStringNotContainsString(route('training.index'), $cleaningGroup[0]);
        $this->assertStringNotContainsString(route('resources.photos'), $cleaningGroup[0]);
        $this->assertStringNotContainsString(route('resources.videos'), $cleaningGroup[0]);
        $this->assertStringNotContainsString(route('resources.guides'), $cleaningGroup[0]);

        preg_match('/<div data-nav-dropdown>.*?data-tour="nav-jobs".*?<div id="nav-jobs-submenu".*?<\/div>\s*<\/div>/s', $navContent, $jobsGroup);
        $this->assertNotEmpty($jobsGroup);
        $this->assertStringContainsString(route('manage.sessions.index'), $jobsGroup[0]);
        $this->assertStringContainsString(route('rooms.index'), $jobsGroup[0]);
        $this->assertStringContainsString(route('tasks.index'), $jobsGroup[0]);

        preg_match('/<details\b[^>]*>.*?data-tour="nav-media".*?<\/details>/s', $navContent, $mediaGroup);
        $this->assertNotEmpty($mediaGroup);
        $this->assertStringContainsString(route('admin.media.index'), $mediaGroup[0]);
        $this->assertStringContainsString(route('admin.videos.index'), $mediaGroup[0]);

        preg_match('/<details\b[^>]*>.*?data-tour="nav-settings".*?<\/details>/s', $navContent, $settingsGroup);
        $this->assertNotEmpty($settingsGroup);
        $this->assertStringNotContainsString(route('admin.videos.index'), $settingsGroup[0]);
        $this->assertStringNotContainsString(route('rooms.index'), $settingsGroup[0]);
        $this->assertStringNotContainsString(route('tasks.index'), $settingsGroup[0]);

        $this->assertSubmenuRendersBelowParent($navContent, 'nav-properties-submenu');
        $this->assertSubmenuRendersBelowParent($navContent, 'nav-settings-submenu');
    }

    private function assertSubmenuRendersBelowParent(string $navigation, string $submenuId): void
    {
        preg_match(
            '/<div data-nav-dropdown>\\s*<div class="flex items-center[^"]*".*?<\\/div>\\s*<div id="'.preg_quote($submenuId, '/').'"/s',
            $navigation,
            $matches
        );

        $this->assertNotEmpty($matches, "Expected submenu {$submenuId} to follow its parent row vertically.");
    }

    private function assertNavigationLink($response, string $url, bool $visible): void
    {
        preg_match('/<nav\\b[^>]*data-tour="sidebar-nav"[^>]*>(.*?)<\\/nav>/s', $response->getContent(), $navigation);
        preg_match_all('/<a\\b[^>]*\\bhref="([^"]+)"/i', $navigation[1] ?? '', $matches);

        $this->assertSame(
            $visible,
            in_array($url, $matches[1], true),
            ($visible ? 'Expected visible navigation link: ' : 'Expected hidden navigation link: ').$url
                .' Available links: '.implode(', ', $matches[1] ?? [])
        );
    }

    private function sidebarHtml($response): string
    {
        preg_match('/<nav\\b[^>]*data-tour="sidebar-nav"[^>]*>(.*?)<\\/nav>/s', $response->getContent(), $navigation);

        return $navigation[1] ?? '';
    }
}
