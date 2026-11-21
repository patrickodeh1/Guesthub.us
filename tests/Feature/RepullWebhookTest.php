<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Channels\RepullConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RepullWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['repull.webhook_secret' => self::SECRET]);
    }

    private function owner(): User
    {
        Role::findOrCreate('owner');
        $user = User::factory()->create();
        $user->assignRole('owner');

        return $user;
    }

    private function send(array $payload, string $event, ?string $secret = self::SECRET, ?string $delivery = null)
    {
        $body = json_encode($payload);
        $t = time();
        $sig = hash_hmac('sha256', $t.'.'.$body, (string) $secret);
        $server = ['HTTP_X_REPULL_SIGNATURE' => "t={$t},v1={$sig}", 'HTTP_X_REPULL_EVENT' => $event, 'CONTENT_TYPE' => 'application/json'];
        if ($delivery) {
            $server['HTTP_X_REPULL_DELIVERY'] = $delivery;
        }

        return $this->call('POST', route('webhooks.repull'), [], [], [], $server, $body);
    }

    private function completed(string $state, string $accountId = '1693389202618766851'): array
    {
        return ['data' => ['state' => $state], 'account' => ['provider' => 'airbnb', 'externalAccountId' => $accountId, 'accessType' => 'full_access']];
    }

    public function test_completed_connect_attaches_the_account_to_the_signed_owner(): void
    {
        $owner = $this->owner();
        $state = app(RepullConnectService::class)->stateFor($owner);

        $this->send($this->completed($state), 'connect.session.completed')->assertOk();

        $this->assertDatabaseHas('channel_connections', [
            'owner_id' => $owner->id, 'channel' => 'airbnb', 'external_account_id' => '1693389202618766851', 'status' => 'connected', 'access_type' => 'full_access',
        ]);
    }

    public function test_forged_state_never_creates_a_connection(): void
    {
        $owner = $this->owner();

        $this->send($this->completed($owner->id.'.'.str_repeat('a', 32)), 'connect.session.completed')->assertOk();

        $this->assertDatabaseCount('channel_connections', 0);
    }

    public function test_an_account_cannot_be_stolen_by_another_host(): void
    {
        $a = $this->owner();
        $b = $this->owner();
        $connect = app(RepullConnectService::class);

        $this->send($this->completed($connect->stateFor($a)), 'connect.session.completed')->assertOk();
        $this->send($this->completed($connect->stateFor($b)), 'connect.session.completed')->assertOk();

        $this->assertDatabaseHas('channel_connections', ['external_account_id' => '1693389202618766851', 'owner_id' => $a->id]);
        $this->assertDatabaseMissing('channel_connections', ['owner_id' => $b->id]);
    }

    public function test_bad_signature_is_rejected(): void
    {
        $this->send(['x' => 1], 'connect.session.completed', 'wrong-secret')->assertStatus(401);
    }

    public function test_unconfigured_secret_returns_503(): void
    {
        config(['repull.webhook_secret' => null]);

        $this->send(['x' => 1], 'connect.session.completed')->assertStatus(503);
    }

    public function test_duplicate_delivery_is_processed_once(): void
    {
        $owner = $this->owner();
        $state = app(RepullConnectService::class)->stateFor($owner);

        $this->send($this->completed($state), 'connect.session.completed', self::SECRET, 'dlv-1')->assertOk();
        $this->send($this->completed($state), 'connect.session.completed', self::SECRET, 'dlv-1')->assertOk()->assertJson(['duplicate' => true]);
    }
}
