<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Charge;
use Stripe\StripeClient;

/**
 * Single point of contact with Stripe. Deliberately not deposit-specific in
 * name/shape — deposit is just the first consumer — so a future
 * registration-fee charge (task 2) or a second processor can reuse this
 * without a redesign.
 *
 * For now: every charge type, including the deposit, is captured
 * immediately (capture_method=automatic) rather than authorized-as-a-hold.
 * This is a deliberate simplification — Stripe auto-cancels an uncaptured
 * hold after 7 days by default and a guest stay can easily exceed that, so
 * a hold-based deposit isn't reliable without more infrastructure (renewing
 * the authorization, handling expiry mid-stay, etc.) than is worth building
 * right now. Refunds for a deposit that turns out not to be owed are a
 * follow-up decision, not handled by this service yet.
 */
class PaymentService
{
    private ?StripeClient $client = null;

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    private function client(): StripeClient
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Stripe is not configured (STRIPE_SECRET missing).');
        }

        return $this->client ??= new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Re-fetches a PaymentIntent's client_secret from Stripe. Used when we
     * reuse an existing pending intent (e.g. on guest page reload) instead
     * of creating a new one.
     */
    public function retrieveClientSecret(string $paymentIntentId): ?string
    {
        return $this->client()->paymentIntents->retrieve($paymentIntentId)->client_secret;
    }

    /**
     * Create a PaymentIntent without a payment method or confirmation — the
     * correct pattern for an embedded Stripe Payment Element, where the
     * guest enters card details directly into Stripe's own embedded
     * component (card data never touches our server) and the client
     * confirms using this intent's client_secret. Creates a local Charge
     * row in 'pending' status; call finalize() after the client reports
     * success to verify with Stripe and mark it captured.
     */
    public function createPendingIntent(Booking $booking, string $type, int $amountCents, string $billingMoment, ?string $description = null): array
    {
        $intent = $this->client()->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => 'usd',
            'payment_method_types' => ['card'],
            'description' => $description ?: "{$type} charge for booking {$booking->booking_id}",
            'metadata' => ['booking_id' => $booking->id, 'type' => $type],
        ]);

        $charge = $booking->charges()->create([
            'type' => $type,
            'amount_cents' => $amountCents,
            'status' => Charge::STATUS_PENDING,
            'stripe_payment_intent_id' => $intent->id,
            'billing_moment' => $billingMoment,
            'description' => $description,
        ]);

        return ['client_secret' => $intent->client_secret, 'charge' => $charge];
    }

    /**
     * Verifies the given PaymentIntent's status directly with Stripe
     * (never trusts the client's own claim of success) and updates the
     * matching local Charge/Booking accordingly.
     */
    public function finalize(string $paymentIntentId): ?Charge
    {
        $charge = Charge::where('stripe_payment_intent_id', $paymentIntentId)->first();

        if (! $charge) {
            return null;
        }

        $intent = $this->client()->paymentIntents->retrieve($paymentIntentId);

        $charge->update([
            'status' => $intent->status === 'succeeded' ? Charge::STATUS_SUCCESS : Charge::STATUS_FAILED,
            'captured_at' => $intent->status === 'succeeded' ? now() : null,
        ]);

        if ($charge->status === Charge::STATUS_SUCCESS && in_array($charge->type, [Charge::TYPE_DEPOSIT, Charge::TYPE_INCIDENTALS], true)) {
            // Whatever the current incidentals_charge is at the moment this
            // charge succeeds is now considered "billed" — the combined
            // pre-checkin charge includes incidentals as of that moment,
            // and a standalone incidentals charge settles it in full. Either
            // way, unbilledIncidentalsCents() should return 0 right after.
            $charge->booking->update([
                'incidentals_billed_cents' => (int) round(($charge->booking->incidentals_charge ?? 0) * 100),
            ]);
        }

        if ($charge->status === Charge::STATUS_SUCCESS && in_array($charge->type, [Charge::TYPE_DEPOSIT, Charge::TYPE_PARKING], true)) {
            // Same idea, for parking: the combined pre-checkin charge (or a
            // standalone parking charge) settles whatever parking currently
            // is, so unbilledParkingCents() should return 0 right after and
            // the guest is never charged parking a second time.
            $charge->booking->update([
                'parking_billed_cents' => (int) round(($charge->booking->effectiveParkingCharge() ?? 0) * 100),
            ]);
        }

        if ($charge->status === Charge::STATUS_SUCCESS && in_array($charge->type, [Charge::TYPE_DEPOSIT, Charge::TYPE_EARLY_CHECKIN], true)) {
            // Same idea, for early check-in.
            $charge->booking->update([
                'early_checkin_billed_cents' => (int) round(($charge->booking->earlyCheckinCharge() ?? 0) * 100),
            ]);
        }

        if ($charge->type === Charge::TYPE_DEPOSIT) {
            $charge->booking->update([
                'deposit_payment_status' => $charge->status,
                'deposit_stripe_payment_intent_id' => $paymentIntentId,
                'deposit_amount_cents' => $charge->amount_cents,
            ]);

            if ($charge->status === Charge::STATUS_SUCCESS && $charge->booking->status === 'awaiting_deposit') {
                $charge->booking->update(['status' => 'deposit_paid']);
            }
        }

        return $charge;
    }

    /**
     * A standalone, immediate, server-initiated charge using an
     * already-tokenized payment method — kept for admin-initiated or
     * non-Elements charge flows. Guest-facing charges should generally use
     * createPendingIntent()+finalize() above instead, so card data stays
     * entirely within Stripe's own embedded component.
     */
    public function charge(Booking $booking, string $type, int $amountCents, string $paymentMethodId, string $billingMoment, ?string $description = null): Charge
    {
        $intent = $this->client()->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => 'usd',
            'payment_method' => $paymentMethodId,
            'capture_method' => 'automatic',
            'confirm' => true,
            'description' => $description ?: "{$type} charge for booking {$booking->booking_id}",
            'metadata' => ['booking_id' => $booking->id, 'type' => $type],
        ]);

        $charge = $booking->charges()->create([
            'type' => $type,
            'amount_cents' => $amountCents,
            'status' => $intent->status === 'succeeded' ? Charge::STATUS_SUCCESS : Charge::STATUS_FAILED,
            'stripe_payment_intent_id' => $intent->id,
            'billing_moment' => $billingMoment,
            'captured_at' => $intent->status === 'succeeded' ? now() : null,
            'description' => $description,
        ]);

        if ($type === Charge::TYPE_DEPOSIT) {
            $booking->update([
                'deposit_payment_status' => $charge->status,
                'deposit_stripe_payment_intent_id' => $intent->id,
                'deposit_amount_cents' => $amountCents,
            ]);

            if ($charge->status === Charge::STATUS_SUCCESS && $booking->status === 'awaiting_deposit') {
                $booking->update(['status' => 'deposit_paid']);
            }
        }

        return $charge;
    }
}
