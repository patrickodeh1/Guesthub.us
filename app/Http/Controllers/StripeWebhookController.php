<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, PaymentService $paymentService)
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret) {
            report(new \RuntimeException('STRIPE_WEBHOOK_SECRET is not configured.'));
            return response()->json(['ok' => false], 500);
        }

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (\UnexpectedValueException $e) {
            ActivityLogService::security('stripe_webhook_invalid_payload', 'Rejected a Stripe webhook with an unparsable payload.', [
                'severity' => 'warning',
            ]);
            return response()->json(['ok' => false, 'error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $e) {
            ActivityLogService::security('stripe_webhook_invalid_signature', 'Rejected a Stripe webhook with an invalid signature.', [
                'severity' => 'warning',
            ]);
            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 401);
        }

        \Illuminate\Support\Facades\Log::info('Stripe webhook received', ['type' => $event->type, 'id' => $event->id]);

        $paymentIntentEvents = [
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
        ];

        if (in_array($event->type, $paymentIntentEvents, true)) {
            $intent = $event->data->object;
            $charge = $paymentService->finalize($intent->id);

            if (! $charge) {
                \Illuminate\Support\Facades\Log::info('Stripe webhook: no matching local charge for payment_intent', [
                    'payment_intent_id' => $intent->id,
                    'event_type' => $event->type,
                ]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
