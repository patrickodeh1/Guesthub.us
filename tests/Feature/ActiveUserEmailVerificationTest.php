<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActiveUserEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_created_users_are_verified(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'status' => 'active',
            'must_change_password' => false,
            'password' => Hash::make('StrongPassword123!'),
        ]);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Housekeeper',
            'email' => 'housekeeper@example.test',
            'password' => 'StrongPassword456!',
            'password_confirmation' => 'StrongPassword456!',
            'role' => 'housekeeper',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'housekeeper@example.test',
            'status' => 'active',
        ]);
        $this->assertNotNull(User::where('email', 'housekeeper@example.test')->value('email_verified_at'));
    }

    public function test_backfill_previews_by_default_and_only_updates_active_unverified_users_on_apply(): void
    {
        $activeUnverified = User::factory()->unverified()->create(['status' => 'active']);
        $inactiveUnverified = User::factory()->unverified()->create(['status' => 'inactive']);
        $activeVerified = User::factory()->create(['status' => 'active']);

        $this->artisan('users:backfill-email-verification')
            ->expectsOutputToContain('Active users missing email verification: 1.')
            ->expectsOutputToContain('Preview only; no records were changed.')
            ->assertExitCode(0);

        $this->assertNull($activeUnverified->fresh()->email_verified_at);

        $this->assertSame(0, Artisan::call('users:backfill-email-verification', ['--apply' => true]));
        $this->assertStringContainsString('Backfill complete. Updated 1 active user(s).', Artisan::output());

        $this->assertNotNull($activeUnverified->fresh()->email_verified_at);
        $this->assertNull($inactiveUnverified->fresh()->email_verified_at);
        $this->assertNotNull($activeVerified->fresh()->email_verified_at);
    }

    public function test_backfill_rejects_conflicting_dry_run_and_apply_options(): void
    {
        $this->artisan('users:backfill-email-verification', [
            '--dry-run' => true,
            '--apply' => true,
        ])
            ->expectsOutput('Choose either --dry-run or --apply, not both.')
            ->assertExitCode(2);
    }
}
