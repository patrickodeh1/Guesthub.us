<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OwnerCannotManageUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_cannot_open_user_management_pages(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $owner->assignRole('owner');

        $this->actingAs($owner)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->get(route('admin.users.create'))->assertForbidden();
    }

    public function test_owner_cannot_promote_themselves_through_user_update_endpoint(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
        ]);
        $owner->assignRole('owner');

        $this->actingAs($owner)
            ->put(route('admin.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'admin',
            ])
            ->assertForbidden();

        $this->assertTrue($owner->fresh()->hasRole('owner'));
        $this->assertFalse($owner->fresh()->hasRole('admin'));
    }
}
