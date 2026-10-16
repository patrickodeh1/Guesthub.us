<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserIndexStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_index_shows_only_active_users_by_default_and_keeps_status_filters(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
            'status' => 'active',
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        $activeUser = User::factory()->create([
            'email' => 'active-user@example.test',
            'is_active' => true,
        ]);
        $inactiveUser = User::factory()->create([
            'email' => 'inactive-user@example.test',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('active-user@example.test')
            ->assertDontSee('inactive-user@example.test');

        $this->get(route('admin.users.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('inactive-user@example.test')
            ->assertDontSee('active-user@example.test');

        $this->get(route('admin.users.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('active-user@example.test')
            ->assertSee('inactive-user@example.test');
    }
}
