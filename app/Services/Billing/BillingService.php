<?php

namespace App\Services\Billing;

use App\Models\HostPackage;
use App\Models\Package;
use App\Models\User;
use Stripe\StripeClient;

class BillingService
{
    private ?StripeClient $client = null;

    private function client(): StripeClient
    {
        if (! filled(config('services.stripe.secret'))) {
            throw new \RuntimeException('Stripe is not configured (STRIPE_SECRET missing).');
        }

        return $this->client ??= new StripeClient(config('services.stripe.secret'));
    }

    /** @param array{lines: array, total_cents: int} $quote @param array<string,int> $addons */
    public function checkoutUrl(User $user, Package $package, array $quote, array $addons, ?HostPackage $existing, string $successUrl, string $cancelUrl): string
    {
        $items = [];
        $addonMeta = [];
        foreach ($quote['lines'] as $l) {
            $items[] = [
                'quantity' => (int) $l['quantity'],
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => (int) $l['unit_cents'],
                    'recurring' => ['interval' => 'month'],
                    'product_data' => ['name' => $l['name']],
                ],
            ];
            if ($l['type'] === 'addon') {
                $addonMeta[$l['key']] = (int) $l['quantity'];
            }
        }

        $meta = [
            'user_id' => (string) $user->id,
            'package_id' => (string) $package->id,
            'addons' => json_encode($addonMeta),
        ];

        $subData = ['metadata' => $meta];
        $trialDays = ($existing && $existing->stripe_customer_id) ? 0 : (int) $package->trial_days;
        if ($trialDays > 0) {
            $subData['trial_period_days'] = $trialDays;
        }

        $params = [
            'mode' => 'subscription',
            'line_items' => $items,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $user->id,
            'payment_method_collection' => 'always',
            'subscription_data' => $subData,
            'metadata' => $meta,
        ];
        if ($existing && $existing->stripe_customer_id) {
            $params['customer'] = $existing->stripe_customer_id;
        } else {
            $params['customer_email'] = $user->email;
        }

        return (string) $this->client()->checkout->sessions->create($params)->url;
    }

    public function setupIntent(User $user, ?HostPackage $existing): string
    {
        $customer = $existing && $existing->stripe_customer_id
            ? $existing->stripe_customer_id
            : $this->client()->customers->create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => ['user_id' => (string) $user->id],
            ])->id;

        return (string) $this->client()->setupIntents->create([
            'customer' => $customer,
            'usage' => 'off_session',
            'payment_method_types' => ['card'],
            'metadata' => ['user_id' => (string) $user->id],
        ])->client_secret;
    }

    /** @param array{lines: array, total_cents: int} $quote @param array<string,int> $addons */
    public function subscribe(User $user, Package $package, array $quote, array $addons, ?HostPackage $existing, string $setupIntentId): \Stripe\Subscription
    {
        $si = $this->client()->setupIntents->retrieve($setupIntentId);
        if ($si->status !== 'succeeded' || ! $si->payment_method || ! $si->customer
            || (string) ($si->metadata['user_id'] ?? '') !== (string) $user->id) {
            throw new \RuntimeException('Card setup was not completed.');
        }

        $this->client()->customers->update($si->customer, [
            'invoice_settings' => ['default_payment_method' => $si->payment_method],
        ]);

        $items = [];
        $addonMeta = [];
        foreach ($quote['lines'] as $l) {
            $product = $this->client()->products->create(['name' => $l['name']]);
            $items[] = [
                'quantity' => (int) $l['quantity'],
                'price_data' => [
                    'currency' => 'usd',
                    'product' => $product->id,
                    'unit_amount' => (int) $l['unit_cents'],
                    'recurring' => ['interval' => 'month'],
                ],
            ];
            if ($l['type'] === 'addon') {
                $addonMeta[$l['key']] = (int) $l['quantity'];
            }
        }

        $params = [
            'customer' => $si->customer,
            'items' => $items,
            'default_payment_method' => $si->payment_method,
            'payment_behavior' => 'error_if_incomplete',
            'metadata' => [
                'user_id' => (string) $user->id,
                'package_id' => (string) $package->id,
                'addons' => json_encode($addonMeta),
            ],
        ];
        $trialDays = ($existing && $existing->stripe_customer_id) ? 0 : (int) $package->trial_days;
        if ($trialDays > 0) {
            $params['trial_period_days'] = $trialDays;
        }

        return $this->client()->subscriptions->create($params);
    }

    /** @param array{lines: array, total_cents: int} $quote */
    public function changeAddons(HostPackage $hp, array $quote, ?\App\Models\Package $package = null, array $addons = []): \Stripe\Subscription
    {
        $sub = $this->client()->subscriptions->retrieve($hp->stripe_subscription_id);

        $items = [];
        $currentCents = 0;
        foreach ($sub->items->data as $it) {
            $items[] = ['id' => $it->id, 'deleted' => true];
            $currentCents += (int) ($it->price->unit_amount ?? 0) * (int) ($it->quantity ?? 1);
        }
        $addonMeta = [];
        foreach ($quote['lines'] as $l) {
            $product = $this->client()->products->create(['name' => $l['name']]);
            $items[] = [
                'quantity' => (int) $l['quantity'],
                'price_data' => [
                    'currency' => 'usd',
                    'product' => $product->id,
                    'unit_amount' => (int) $l['unit_cents'],
                    'recurring' => ['interval' => 'month'],
                ],
            ];
            if ($l['type'] === 'addon') {
                $addonMeta[$l['key']] = (int) $l['quantity'];
            }
        }

        // Keep zero-quantity resource add-ons in metadata. If the host later
        // adds a property or device, the scheduled reconciler can restore the
        // paid line without asking the host to select the add-on again.
        foreach ($addons as $key => $quantity) {
            $addonMeta[$key] = max(0, (int) $quantity);
        }

        $params = [
            'items' => $items,
            'proration_behavior' => 'none', // changes bill from the next cycle, no prorations or credits
            'metadata' => ['addons' => json_encode($addonMeta)],
        ];
        if ($package) {
            $params['metadata']['package_id'] = (string) $package->id;
        }

        $result = $this->client()->subscriptions->update($hp->stripe_subscription_id, $params);
        \Illuminate\Support\Facades\Log::info('changeAddons billing', [
            'stripe_status_before' => $sub->status,
            'current_cents' => $currentCents,
            'quote_total_cents' => (int) $quote['total_cents'],
            'proration_behavior' => $params['proration_behavior'],
            'payment_behavior' => $params['payment_behavior'] ?? null,
            'latest_invoice' => is_string($result->latest_invoice ?? null) ? $result->latest_invoice : ($result->latest_invoice->id ?? null),
            'pending_update' => ! empty($result->pending_update),
        ]);

        return $result;
    }

    public function portalUrl(string $customerId, string $returnUrl): string
    {
        return (string) $this->client()->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
        ])->url;
    }
}
