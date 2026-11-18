<?php

namespace Tests\\Feature;

use App\\Models\\Property;
use App\\Models\\PropertyLock;
use App\\Models\\SeamConnectedAccount;
use App\\Models\\User;
use App\\Services\\SeamService;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Mockery;
use Spatie\\Permission\\Models\\Role;
use Tests\\TestCase;

class SeamConnectionTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        Role::findOrCreate('owner');
        $user = User::factory()->create();
        $user->assignRole('owner');

        return $user;
    }

    public function test_host_can_start_a_yale_and_august_connection_for_their_property(): void
    {
        $host = $this->owner();
        $property = Property::factory()->create(['owner_id' => $host->id]);
        $seam = Mockery::mock(SeamService::class);
        $seam->shouldReceive('createConnectWebview')
            ->once()
            ->with(
                Mockery::on(fn ($user) => $user->id === $host->id),
                route('admin.locks.connect.return', $property),
                route('admin.locks.connect.failed', $property),
            )
            ->andReturn([
                'connect_webview_id' => 'cw_yale_august',
                'url' => 'https://connect.getseam.com/connect_webviews/view?connect_webview_id=cw_yale_august',
            ]);
        $this->app->instance(SeamService::class, $seam);

        $this->actingAs($host)
            ->post(route('admin.locks.connect', $property))
            ->assertRedirect('https://connect.getseam.com/connect_webviews/view?connect_webview_id=cw_yale_august');

        $this->assertSame('cw_yale_august', session('seam.pending.'.$property->id.'.connect_webview_id'));
        $this->assertSame($host->id, session('seam.pending.'.$property->id.'.host_id'));
    }

    public function test_successful_connect_callback_saves_the_connected_account_to_the_property_owner(): void
    {
        $host = $this->owner();
        $property = Property::factory()->create(['owner_id' => $host->id]);
        $seam = Mockery::mock(SeamService::class);
        $seam->shouldReceive('getConnectWebview')->once()->with('cw_yale')->andReturn([
            'status' => 'authorized',
            'login_successful' => true,
            'connected_account_id' => 'ca_yale_host_a',
            'selected_provider' => 'yale',
        ]);
        $seam->shouldReceive('listDevices')->once()->with('ca_yale_host_a')->andReturn([
            ['device_id' => 'device_yale_a', 'display_name' => 'Front Door'],
        ]);
        $this->app->instance(SeamService::class, $seam);

        $this->withSession([
            'seam.pending.'.$property->id => [
                'host_id' => $host->id,
                'connect_webview_id' => 'cw_yale',
            ],
        ])->actingAs($host)
            ->get(route('admin.locks.connect.return', $property))
            ->assertRedirect(route('admin.properties.edit', $property))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('seam_connected_accounts', [
            'user_id' => $host->id,
            'seam_connected_account_id' => 'ca_yale_host_a',
            'provider' => 'yale',
            'status' => 'connected',
        ]);
    }

    public function test_host_cannot_assign_another_hosts_seam_device(): void
    {
        $hostA = $this->owner();
        $hostB = $this->owner();
        $propertyA = Property::factory()->create(['owner_id' => $hostA->id]);
        SeamConnectedAccount::create([
            'user_id' => $hostB->id,
            'seam_connected_account_id' => 'ca_host_b',
            'provider' => 'august',
            'status' => 'connected',
        ]);

        $seam = Mockery::mock(SeamService::class);
        $seam->shouldReceive('findDeviceForHost')
            ->once()
            ->with(Mockery::on(fn ($user) => $user->id === $hostA->id), 'device_host_b')
            ->andReturn(null);
        $this->app->instance(SeamService::class, $seam);

        $this->actingAs($hostA)
            ->post(route('admin.properties.locks.store', $propertyA), [
                'seam_device_id' => 'device_host_b',
                'label' => 'Other host lock',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('property_locks', [
            'property_id' => $propertyA->id,
            'seam_device_id' => 'device_host_b',
        ]);
    }

    public function test_host_can_assign_a_device_from_their_own_connected_account(): void
    {
        $host = $this->owner();
        $property = Property::factory()->create(['owner_id' => $host->id]);
        SeamConnectedAccount::create([
            'user_id' => $host->id,
            'seam_connected_account_id' => 'ca_host',
            'provider' => 'august',
            'status' => 'connected',
        ]);

        $seam = Mockery::mock(SeamService::class);
        $seam->shouldReceive('findDeviceForHost')
            ->once()
            ->with(Mockery::on(fn ($user) => $user->id === $host->id), 'device_host')
            ->andReturn([
                'device_id' => 'device_host',
                'display_name' => 'Front Door',
                'device_provider' => ['provider_name' => 'august'],
            ]);
        $this->app->instance(SeamService::class, $seam);

        $this->actingAs($host)
            ->post(route('admin.properties.locks.store', $property), [
                'seam_device_id' => 'device_host',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('property_locks', [
            'property_id' => $property->id,
            'seam_device_id' => 'device_host',
            'label' => 'Front Door',
        ]);
    }
}
