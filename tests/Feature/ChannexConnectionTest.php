<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\User;
use App\Services\Pms\ChannexProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChannexConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.channex.api_key' => 'test-channex-key',
            'services.channex.default_currency' => 'USD',
        ]);
    }

    private function owner(): User
    {
        Role::findOrCreate('owner');
        $user = User::factory()->create();
        $user->assignRole('owner');

        return $user;
    }

    public function test_owner_can_create_channex_property_room_and_rate_plan_without_duplicate_setup(): void
    {
        $host = $this->owner();
        $property = Property::factory()->create([
            'owner_id' => $host->id,
            'name' => 'GuestHub Test Home',
            'beds' => 2,
            'timezone' => 'America/New_York',
            'country' => 'US',
        ]);

        $channex = Mockery::mock(ChannexProvider::class);
        $channex->shouldReceive('verifyConnection')->once()->andReturn([
            'ok' => true, 'status' => 200, 'environment' => 'staging',
        ]);
        $channex->shouldReceive('createProperty')->once()->with(
            Mockery::on(fn ($payload) => $payload['title'] === 'GuestHub Test Home'
                && $payload['currency'] === 'USD'
                && $payload['country'] === 'US'
                && $payload['timezone'] === 'America/New_York'),
            'guesthub:'.$property->id,
        )->andReturn(['data' => ['id' => 'ch-property-1']]);
        $channex->shouldReceive('createRoomType')->once()->with(
            Mockery::on(fn ($payload) => $payload['property_id'] === 'ch-property-1'
                && $payload['count_of_rooms'] === 1
                && $payload['occ_adults'] === 4),
            'ch-property-1',
        )->andReturn(['data' => ['id' => 'ch-room-1']]);
        $channex->shouldReceive('createRatePlan')->once()->with(
            Mockery::on(fn ($payload) => $payload['room_type_id'] === 'ch-room-1'
                && $payload['options'][0]['occupancy'] === 2
                && $payload['options'][0]['is_primary'] === true),
            'ch-property-1',
        )->andReturn(['data' => ['id' => 'ch-rate-1']]);
        $this->app->instance(ChannexProvider::class, $channex);

        $this->actingAs($host)
            ->post(route('admin.properties.channex.create', $property))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('ch-property-1', $property->fresh()->channex_property_id);
        $this->assertSame('ch-room-1', $property->fresh()->channex_room_type_id);
        $this->assertSame('ch-rate-1', $property->fresh()->channex_rate_plan_id);
    }

    public function test_owner_gets_property_scoped_one_time_channel_iframe_without_persisting_token(): void
    {
        $host = $this->owner();
        $property = Property::factory()->create([
            'owner_id' => $host->id,
            'channex_property_id' => 'ch-property-1',
        ]);

        $channex = Mockery::mock(ChannexProvider::class);
        $channex->shouldReceive('createOneTimeToken')
            ->once()
            ->with('ch-property-1', $host->name)
            ->andReturn('one-use-iframe-token');
        $channex->shouldReceive('baseServerUrl')->once()->andReturn('https://staging.channex.io');
        $this->app->instance(ChannexProvider::class, $channex);

        $response = $this->actingAs($host)
            ->get(route('admin.properties.channex.channels', $property))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('https://staging.channex.io/auth/exchange?token=one-use-iframe-token', false);

        $this->assertArrayNotHasKey('channex_iframe_token', $property->fresh()->getAttributes());
    }

    public function test_owner_cannot_open_another_hosts_channex_iframe(): void
    {
        $hostA = $this->owner();
        $hostB = $this->owner();
        $propertyB = Property::factory()->create([
            'owner_id' => $hostB->id,
            'channex_property_id' => 'ch-property-b',
        ]);

        $this->actingAs($hostA)
            ->get(route('admin.properties.channex.channels', $propertyB))
            ->assertForbidden();
    }
}
