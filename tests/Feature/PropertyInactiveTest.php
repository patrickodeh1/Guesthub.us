<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyInactiveTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email_verified_at' => now(),
            'status' => 'active',
            'must_change_password' => false,
            'password' => bcrypt('StrongPassword123!'),
        ], $attributes));
    }

    public function test_property_can_be_deactivated()
    {
        $admin = $this->createUser();
        $admin->assignRole('admin');
        
        $property = Property::factory()->create(['is_active' => true, 'owner_id' => $this->createUser()->id]);

        $response = $this->actingAs($admin)->delete(route('properties.destroy', $property));

        $response->assertRedirect(route('properties.index'));
        $response->assertSessionHas('ok', 'Property marked inactive.');

        $this->assertFalse($property->fresh()->is_active);
        $this->assertNotNull($property->fresh()->deactivated_at);
        $this->assertEquals($admin->id, $property->fresh()->deactivated_by);
    }

    public function test_property_can_be_reactivated()
    {
        $admin = $this->createUser();
        $admin->assignRole('admin');
        
        $property = Property::factory()->create([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $admin->id,
            'owner_id' => $this->createUser()->id
        ]);

        $response = $this->actingAs($admin)->post(route('properties.activate', $property));

        $response->assertRedirect();
        $response->assertSessionHas('ok', 'Property reactivated.');

        $this->assertTrue($property->fresh()->is_active);
        $this->assertNull($property->fresh()->deactivated_at);
        $this->assertNull($property->fresh()->deactivated_by);
    }

    public function test_active_scope_filters_correctly()
    {
        $owner = $this->createUser();
        Property::factory()->count(3)->create(['is_active' => true, 'owner_id' => $owner->id]);
        Property::factory()->count(2)->create(['is_active' => false, 'owner_id' => $owner->id]);

        $this->assertEquals(3, Property::active()->count());
        $this->assertEquals(2, Property::inactive()->count());
        $this->assertEquals(5, Property::withInactive()->count());
    }

    public function test_cleaning_properties_index_redirects_to_canonical_admin_list()
    {
        $admin = $this->createUser();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('properties.index'));

        $response->assertRedirect(route('admin.properties.index'));
    }

    public function test_canonical_property_list_shows_active_and_inactive_properties()
    {
        $admin = $this->createUser();
        $admin->assignRole('admin');
        
        $activeProperty = Property::factory()->create(['is_active' => true, 'name' => 'Active House', 'owner_id' => $this->createUser()->id]);
        $inactiveProperty = Property::factory()->create(['is_active' => false, 'name' => 'Inactive House', 'owner_id' => $this->createUser()->id]);

        $response = $this->actingAs($admin)->get(route('admin.properties.index'));

        $response->assertSee('Active House');
        $response->assertSee('Inactive House');
    }

    public function test_housekeeper_cannot_deactivate_property()
    {
        $housekeeper = $this->createUser();
        $housekeeper->assignRole('housekeeper');
        
        $property = Property::factory()->create(['is_active' => true, 'owner_id' => $this->createUser()->id]);

        $response = $this->actingAs($housekeeper)->delete(route('properties.destroy', $property));

        $response->assertForbidden();
        $this->assertTrue($property->fresh()->is_active);
    }
}
