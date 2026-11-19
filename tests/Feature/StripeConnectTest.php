<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StripeConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_deauthorization_clears_only_the_matching_connected_host_account(): void
    {
        config(['services.stripe.connect_webhook_secret' => 'whsec_connect_test']);
        $hostA = User::factory()->create();
        $hostB = User::factory()->create();
        $hostA->forceFill([
            'stripe_account_id' => 'acct_host_a',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
        ])->save();
        $hostB->forceFill([
            'stripe_account_id' => 'acct_host_b',
            'stripe_charges_enabled' => true,
            'stripe_payouts_enabled' => true,
        ])->save();
        $this->app->instance(PaymentService::class, Mockery::mock(PaymentService::class));

        $payload = json_encode([
            'id' => 'evt_deauthorized_a',
            'object' => 'event',
            'type' => 'account.application.deauthorized',
            'account' => 'acct_host_a',
            'data' => ['object' => ['id' => 'ca_guesthub_application', 'object' => 'application']],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_connect_test');

        $this->call('POST', route('webhooks.stripe.connect'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload)->assertOk()->assertJson(['ok' => true]);

        $this->assertNull($hostA->fresh()->stripe_account_id);
        $this->assertFalse((bool) $hostA->fresh()->stripe_charges_enabled);
        $this->assertFalse((bool) $hostA->fresh()->stripe_payouts_enabled);
        $this->assertSame('acct_host_b', $hostB->fresh()->stripe_account_id);
        $this->assertTrue((bool) $hostB->fresh()->stripe_charges_enabled);
        $this->assertTrue((bool) $hostB->fresh()->stripe_payouts_enabled);
    }

    public function test_connect_webhook_rejects_missing_signing_secret(): void
    {
        config(['services.stripe.connect_webhook_secret' => null]);
        $this->app->instance(PaymentService::class, Mockery::mock(PaymentService::class));

        $this->postJson(route('webhooks.stripe.connect'), [])
            ->assertStatus(500)
            ->assertJson(['ok' => false]);
    }
}
