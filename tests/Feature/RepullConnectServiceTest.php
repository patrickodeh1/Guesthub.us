<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Channels\RepullConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepullConnectServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'repull.enabled' => true,
            'repull.api_key' => 'repull-test-key',
            'repull.base_url' => 'https://api.repull.dev',
        ]);
    }

    public function test_airbnb_full_access_uses_provider_endpoint_and_signed_redirect_state(): void
    {
        Http::fake([
            'api.repull.dev/v1/connect/airbnb' => Http::response(['url' => 'https://connect.repull.dev/session'], 201),
        ]);
        $owner = User::factory()->create();

        $url = app(RepullConnectService::class)->start($owner, ['airbnb'], 'full_access');

        $this->assertSame('https://connect.repull.dev/session', $url);
        Http::assertSent(function ($request) use ($owner) {
            $redirect = (string) $request['redirectUrl'];
            parse_str((string) parse_url($redirect, PHP_URL_QUERY), $query);
            return $request->url() === 'https://api.repull.dev/v1/connect/airbnb'
                && $request['accessType'] === 'full_access'
                && isset($query['state'])
                && str_starts_with($query['state'], $owner->id.'.');
        });
    }

    public function test_booking_extranet_connect_is_messaging_only_and_uses_generic_provider_flow(): void
    {
        Http::fake([
            'api.repull.dev/v1/connect' => Http::response(['url' => 'https://connect.repull.dev/booking-extranet'], 201),
        ]);
        $owner = User::factory()->create();

        $url = app(RepullConnectService::class)->start($owner, ['booking'], 'messaging', 'extranet');

        $this->assertSame('https://connect.repull.dev/booking-extranet', $url);
        Http::assertSent(function ($request) {
            parse_str((string) parse_url((string) $request['redirectUrl'], PHP_URL_QUERY), $query);
            return $request->url() === 'https://api.repull.dev/v1/connect'
                && $request['allowedProviders'] === ['booking-extranet-login']
                && ! array_key_exists('accessType', $request->data())
                && ($query['accessType'] ?? null) === 'messaging';
        });
    }
}
