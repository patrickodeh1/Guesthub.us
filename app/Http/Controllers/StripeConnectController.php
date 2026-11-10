<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeConnectController extends Controller
{
    public function onboard(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'admin']), 403);
        abort_unless(filled(config('services.stripe.secret')), 503, 'Stripe is not configured.');
        abort_unless(filled(config('services.stripe.client_id')), 503, 'Stripe Connect is not configured.');

        $state = Str::random(40);
        $request->session()->put('stripe_connect_state', $state);

        return redirect()->away('https://connect.stripe.com/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.stripe.client_id'),
            'scope' => 'read_write',
            'redirect_uri' => route('admin.connect.return'),
            'state' => $state,
        ]));
    }

    public function returned(Request $request)
    {
        $user = $request->user();
        abort_unless(filled(config('services.stripe.secret')), 503, 'Stripe is not configured.');
        $expectedState = $request->session()->pull('stripe_connect_state');
        $receivedState = $request->query('state');
        abort_unless(
            is_string($expectedState) && is_string($receivedState) && hash_equals($expectedState, $receivedState),
            403,
        );
        if ($request->query('error')) {
            return redirect()->route('admin.properties.index')->with('status', 'Stripe account connection was not completed.');
        }

        abort_unless(filled(config('services.stripe.client_id')), 503, 'Stripe Connect is not configured.');
        $data = $request->validate(['code' => ['required', 'string', 'max:2048']]);
        $stripe = new StripeClient(config('services.stripe.secret'));
        $token = $stripe->oauth->token([
            'grant_type' => 'authorization_code',
            'code' => $data['code'],
        ]);
        abort_unless(filled($token->stripe_user_id ?? null), 502, 'Stripe did not return a connected account.');
        $user->forceFill(['stripe_account_id' => $token->stripe_user_id])->save();
        $account = $stripe->accounts->retrieve($user->stripe_account_id, []);
        $this->syncCapabilities($user, $account);

        return redirect()->route('admin.properties.index')->with(
            'success',
            $user->stripe_charges_enabled ? 'Stripe card payments are connected.' : 'Stripe onboarding is not complete yet.',
        );
    }

    public function refresh()
    {
        return redirect()->route('admin.connect.onboard');
    }

    public function webhook(Request $request, PaymentService $paymentService)
    {
        $secret = config('services.stripe.connect_webhook_secret');
        if (! $secret) {
            report(new \RuntimeException('STRIPE_CONNECT_WEBHOOK_SECRET is not configured.'));

            return response()->json(['ok' => false], 500);
        }

        try {
            $event = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature'), $secret);
        } catch (\UnexpectedValueException) {
            return response()->json(['ok' => false, 'error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException) {
            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 401);
        }

        if ($event->type === 'account.updated') {
            $account = $event->data->object;
            $user = User::query()->where('stripe_account_id', $account->id)->first();
            if ($user) {
                $this->syncCapabilities($user, $account);
            } else {
                Log::warning('Stripe Connect account.updated event has no matching user', ['account_id' => $account->id]);
            }
        }
        if (in_array($event->type, ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.canceled'], true)) {
            $intent = $event->data->object;
            $charge = $paymentService->finalize((string) $intent->id);
            if (! $charge) {
                Log::info('Stripe Connect webhook has no matching local charge', [
                    'payment_intent_id' => $intent->id,
                    'event_type' => $event->type,
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }

    private function syncCapabilities(User $user, object $account): void
    {
        $user->forceFill([
            'stripe_charges_enabled' => (bool) ($account->charges_enabled ?? false),
            'stripe_payouts_enabled' => (bool) ($account->payouts_enabled ?? false),
        ])->save();
    }
}
