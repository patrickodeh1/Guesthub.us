<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_password_as_password_is_redirected()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'must_change_password' => false,
        ]);
        
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect(route('password.force-change'));
    }

    public function test_user_with_normal_password_has_access()
    {
        $user = User::factory()->create([
            'password' => Hash::make('StrongPassword123!'),
            'must_change_password' => false,
        ]);
        
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_user_can_submit_new_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->post(route('password.force-change.store'), [
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertFalse(Hash::check('password', $user->fresh()->password));
        $this->assertTrue(Hash::check('NewSecurePassword123!', $user->fresh()->password));
    }

    public function test_user_cannot_set_password_to_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->actingAs($user)->post(route('password.force-change.store'), [
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
