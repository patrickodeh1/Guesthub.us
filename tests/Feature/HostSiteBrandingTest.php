<?php

namespace Tests\Feature;

use App\Models\HostSite;
use App\Models\HostSiteAlias;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HostSiteBrandingTest extends TestCase
{
    public function test_host_site_slug_changes_create_a_redirect_alias_and_auto_suffix_collisions(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $site = HostSite::create([
            'user_id' => $admin->id,
            'slug' => 'old-host-name',
            'display_name' => 'Old Host Name',
            'brand_color' => '#17221e',
            'show_powered_by' => true,
        ]);
        HostSite::create([
            'user_id' => User::factory()->create()->id,
            'slug' => 'new-host-name',
            'display_name' => 'Another Host',
            'brand_color' => '#17221e',
            'show_powered_by' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.host-site.update'), [
            'slug' => 'new-host-name',
            'display_name' => 'New Host Name',
            'brand_color' => '#334455',
            'support_email' => 'host@example.com',
            'show_powered_by' => '1',
        ])->assertRedirect();

        $site->refresh();
        $this->assertSame('new-host-name-2', $site->slug);
        $this->assertDatabaseHas('host_site_aliases', [
            'host_site_id' => $site->id,
            'slug' => 'old-host-name',
        ]);
        $this->assertDatabaseHas('host_sites', [
            'id' => $site->id,
            'brand_color' => '#334455',
            'support_email' => 'host@example.com',
            'show_powered_by' => 1,
        ]);
    }

    public function test_old_host_site_slug_redirects_to_the_current_slug(): void
    {
        $owner = User::factory()->create();
        $site = HostSite::create([
            'user_id' => $owner->id,
            'slug' => 'current-host',
            'display_name' => 'Current Host',
            'brand_color' => '#17221e',
            'show_powered_by' => true,
        ]);
        HostSiteAlias::create(['host_site_id' => $site->id, 'slug' => 'previous-host']);

        $this->get(route('public.host.show', 'previous-host'))
            ->assertRedirect(route('public.host.show', 'current-host'));
    }
}
