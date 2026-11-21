<?php

namespace Tests\Feature;

use App\Services\Repull\RepullClient;
use App\Services\Repull\RepullException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepullClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['repull.enabled' => true, 'repull.api_key' => 'sk_live_test', 'repull.base_url' => 'https://api.repull.dev']);
    }

    public function test_sends_bearer_and_idempotency_key_and_keeps_big_ids_as_strings(): void
    {
        Http::fake(['api.repull.dev/*' => Http::response('{"id":"48211","hostId":1693389202618766851}', 201, ['Content-Type' => 'application/json'])]);

        $res = (new RepullClient())->createListing(['name' => 'Loft'], 'idem-1');

        $this->assertSame('48211', $res['id']);
        $this->assertSame('1693389202618766851', (string) $res['hostId']); // exact, not rounded to a float
        $this->assertFalse(is_float($res['hostId']));
        Http::assertSent(fn ($r) => $r->url() === 'https://api.repull.dev/v1/listings'
            && $r->hasHeader('Authorization', 'Bearer sk_live_test')
            && $r->hasHeader('Idempotency-Key', 'idem-1'));
    }

    public function test_maps_validation_errors_as_non_retryable_and_rate_limits_as_retryable(): void
    {
        Http::fake([
            'api.repull.dev/v1/listings/1/content' => Http::response(['error' => ['code' => 'invalid_params', 'message' => 'bad', 'field' => 'rooms']], 422),
            'api.repull.dev/v1/listings/2/content' => Http::response(['error' => ['code' => 'rate_limited', 'message' => 'slow down']], 429),
        ]);
        $client = new RepullClient();

        try {
            $client->updateListingContent('1', []);
            $this->fail('expected exception');
        } catch (RepullException $e) {
            $this->assertSame('invalid_params', $e->errorCode);
            $this->assertFalse($e->retryable);
        }

        try {
            $client->updateListingContent('2', []);
            $this->fail('expected exception');
        } catch (RepullException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_refuses_to_call_when_not_configured(): void
    {
        config(['repull.enabled' => false]);
        Http::fake();

        $this->expectException(RepullException::class);
        (new RepullClient())->getPublishStatus('1');
    }

    public function test_channel_connect_uses_provider_specific_endpoint_and_full_access_for_airbnb(): void
    {
        Http::fake(['api.repull.dev/v1/connect/airbnb' => Http::response(['url' => 'https://connect.repull.dev/cs_test'], 201)]);
        $response = (new RepullClient())->createChannelConnectSession('airbnb', [
            'redirectUrl' => 'https://guesthub.test/welcome/channels/connected',
            'state' => 'owner-state',
            'accessType' => 'full_access',
        ]);

        $this->assertSame('https://connect.repull.dev/cs_test', $response['url']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.repull.dev/v1/connect/airbnb'
            && $request['accessType'] === 'full_access'
            && $request['state'] === 'owner-state');
    }

}
