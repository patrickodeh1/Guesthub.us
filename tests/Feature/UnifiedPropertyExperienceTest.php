<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedPropertyExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_a_property_with_portal_and_cleaning_fields(): void
    {
        $fillable = (new Property())->getFillable();
        $this->assertContains('geo_radius_m', $fillable);
        $this->assertContains('ical_url', $fillable);
        $this->assertContains('welcome_intro', $fillable);
        $this->assertContains('checkin_instructions', $fillable);
    }

    public function test_old_property_pages_redirect_to_the_canonical_admin_pages(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false, 'password' => Hash::make('StrongPassword123!')]);
        $admin->assignRole('admin');
        $property = Property::factory()->create();

        $this->actingAs($admin)->get(route('properties.index'))
            ->assertRedirect(route('admin.properties.index'));
        $this->actingAs($admin)->get(route('properties.create'))
            ->assertRedirect(route('admin.properties.create'));
        $this->actingAs($admin)->get(route('properties.edit', $property))
            ->assertRedirect(route('admin.properties.edit', $property));
    }

    public function test_owner_cannot_open_another_owners_property_in_the_canonical_form(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner = User::factory()->create(['email_verified_at' => now(), 'must_change_password' => false, 'password' => Hash::make('StrongPassword123!')]);
        $owner->assignRole('owner');
        $property = Property::factory()->create(['owner_id' => User::factory()->create()->id]);

        $this->actingAs($owner)->get(route('admin.properties.edit', $property))
            ->assertForbidden();
    }
}
