<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\LeaseApplication;
use App\Models\Setting;
use App\Services\DirectBookingService;
use App\Services\LeaseApplicationNotificationService;
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
     * Create a host-account PaymentIntent for a direct booking. The amount
     * and platform fee come only from the persisted server-side booking.
     *
     * @return array{client_secret: string, charge: Charge}
     */
    public function createDirectBookingIntent(Booking $booking, string $connectedAccountId): array
    {
        if ($booking->source !== 'direct' || $booking->status !== Booking::STATUS_PENDING_PAYMENT
            || ($booking->direct_booking_request && ! $booking->direct_booking_approved_at)
            || ($booking->hold_expires_at && $booking->hold_expires_at->isPast())) {
            throw new \LogicException('The booking is not eligible for card payment.');
        }

        $existing = $booking->charges()
            ->where('type', Charge::TYPE_ACCOMMODATION)
            ->where('status', Charge::STATUS_PENDING)
            ->latest()
            ->first();

        if ($existing) {
            if ($existing->stripe_account_id !== $connectedAccountId
                || $existing->amount_cents !== $booking->total_cents) {
                throw new \LogicException('The pending booking payment does not match the current charge details.');
            }

            $intent = $this->client()->paymentIntents->retrieve(
                $existing->stripe_payment_intent_id,
                [],
                ['stripe_account' => $connectedAccountId],
            );
            if ($intent->status === 'succeeded') {
                $this->finalize($existing->stripe_payment_intent_id);
            }

            return ['client_secret' => $intent->client_secret, 'charge' => $existing];
        }

        $amountCents = (int) $booking->total_cents;
        if ($amountCents < 1) {
            throw new \InvalidArgumentException('A direct booking payment must be greater than zero.');
        }

        $platformFee = min($amountCents - 1, $this->platformFeeCents($amountCents) + max(0, (int) $booking->processing_fee_cents));
        $parameters = [
            'amount' => $amountCents,
            'currency' => 'usd',
            'payment_method_types' => ['card'],
            'description' => "Direct booking {$booking->booking_id}",
            'receipt_email' => $booking->email,
            'metadata' => [
                'booking_id' => (string) $booking->id,
                'booking_reference' => $booking->booking_id,
                'type' => Charge::TYPE_ACCOMMODATION,
            ],
        ];
        if ($platformFee > 0) {
            $parameters['application_fee_amount'] = $platformFee;
        }

        $intent = $this->client()->paymentIntents->create(
            $parameters,
            [
                'stripe_account' => $connectedAccountId,
                'idempotency_key' => 'direct-booking-'.$booking->id.'-attempt-'.$booking->charges()->where('type', Charge::TYPE_ACCOMMODATION)->count(),
            ],
        );
        $charge = $booking->charges()->create([
            'type' => Charge::TYPE_ACCOMMODATION,
            'amount_cents' => $amountCents,
            'status' => Charge::STATUS_PENDING,
            'stripe_payment_intent_id' => $intent->id,
            'stripe_account_id' => $connectedAccountId,
            'billing_moment' => 'direct_booking_checkout',
            'description' => 'Accommodation and booking fees',
        ]);

        return ['client_secret' => $intent->client_secret, 'charge' => $charge];
    }

    /**
     * Create a connected-account PaymentIntent for a lease application fee.
     *
     * @return array{client_secret: string, charge: Charge}
     */
    public function createLeaseApplicationIntent(LeaseApplication $application, string $connectedAccountId): array
    {
        if ($application->status !== 'submitted'
            || $application->payment_method !== 'card'
            || $application->fee_status !== 'pending'
            || $application->total_fee_cents < 1) {
            throw new \LogicException('The lease application is not eligible for card payment.');
        }

        $existing = $application->charges()
            ->where('type', Charge::TYPE_APPLICATION_FEE)
            ->where('status', Charge::STATUS_PENDING)
            ->latest()
            ->first();

        if ($existing) {
            if ($existing->stripe_account_id !== $connectedAccountId
                || $existing->amount_cents !== $application->total_fee_cents) {
                throw new \LogicException('The pending application fee does not match the current charge details.');
            }

            $intent = $this->client()->paymentIntents->retrieve(
                $existing->stripe_payment_intent_id,
                [],
                ['stripe_account' => $connectedAccountId],
            );
            if ($intent->status === 'succeeded') {
                $this->finalize($existing->stripe_payment_intent_id);
            }

            return ['client_secret' => $intent->client_secret, 'charge' => $existing];
        }

        $intent = $this->client()->paymentIntents->create(
            [
                'amount' => (int) $application->total_fee_cents,
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'description' => "Lease application fee {$application->id}",
                'receipt_email' => $application->applicant_email,
                'metadata' => [
                    'lease_application_id' => (string) $application->id,
                    'type' => Charge::TYPE_APPLICATION_FEE,
                ],
            ],
            [
                'stripe_account' => $connectedAccountId,
                'idempotency_key' => 'lease-application-'.$application->id.'-fee-attempt-'.$application->charges()
                    ->where('type', Charge::TYPE_APPLICATION_FEE)
                    ->count(),
            ],
        );

        $charge = $application->charges()->create([
            'type' => Charge::TYPE_APPLICATION_FEE,
            'amount_cents' => (int) $application->total_fee_cents,
            'status' => Charge::STATUS_PENDING,
            'stripe_payment_intent_id' => $intent->id,
            'stripe_account_id' => $connectedAccountId,
            'billing_moment' => 'lease_application',
            'description' => 'Lease application fee and card processing fee',
        ]);

        return ['client_secret' => $intent->client_secret, 'charge' => $charge];
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

    public function createLeaseOfferInstallmentIntent(\App\Models\LeaseOffer $offer, \App\Models\LeaseInstallment $installment): array
    {
        $offer->loadMissing(['property.owner', 'booking']);
        abort_unless(in_array($offer->status, [\App\Models\LeaseOffer::STATUS_SIGNED, \App\Models\LeaseOffer::STATUS_CONFIRMED], true)
            && ($offer->status === \App\\Models\\LeaseOffer::STATUS_CONFIRMED || $offer->accept_by?->isFuture())
            && $offer->payment_method === 'card'
            && $installment->lease_offer_id === $offer->id
            && $installment->status === \App\Models\LeaseInstallment::STATUS_PENDING, 409);
        $owner = $offer->property->owner;
        abort_unless(filled($owner?->stripe_account_id) && $owner->stripe_charges_enabled && $this->isConfigured(), 422, 'Card payments are not available for this host.');
        $existing = \App\Models\Charge::query()->where('lease_installment_id', $installment->id)->where('status', \App\Models\Charge::STATUS_PENDING)->latest()->first();
        if ($existing) {
            $intent = $this->client()->paymentIntents->retrieve($existing->stripe_payment_intent_id, [], ['stripe_account' => $owner->stripe_account_id]);
            if ($intent->status === 'succeeded') {
                $this->finalize($intent->id);
                return ['client_secret' => $intent->client_secret, 'charge' => $existing];
            }
            if ($intent->status !== 'canceled') {
                return ['client_secret' => $intent->client_secret, 'charge' => $existing];
            }
            $existing->update(['status' => Charge::STATUS_FAILED]);
        }
        $intent = $this->client()->paymentIntents->create([
            'amount' => (int) $installment->amount_cents,
            'currency' => 'usd',
            'payment_method_types' => ['card'],
            'description' => 'Lease installment '.$installment->label,
            'receipt_email' => $offer->application->applicant_email,
            'metadata' => ['lease_offer_id' => (string) $offer->id, 'lease_installment_id' => (string) $installment->id, 'type' => 'lease_installment'],
        ], ['stripe_account' => $owner->stripe_account_id, 'idempotency_key' => 'lease-installment-'.$installment->id.'-attempt-'.$installment->charges()->count()]);
        $charge = \App\Models\Charge::create([
            'booking_id' => $offer->booking_id,
            'lease_application_id' => $offer->lease_application_id,
            'lease_offer_id' => $offer->id,
            'lease_installment_id' => $installment->id,
            'type' => 'lease_installment',
            'amount_cents' => (int) $installment->amount_cents,
            'status' => \App\Models\Charge::STATUS_PENDING,
            'stripe_payment_intent_id' => $intent->id,
            'stripe_account_id' => $owner->stripe_account_id,
            'billing_moment' => 'lease_installment',
            'description' => $installment->label,
        ]);
        $installment->update(['charge_id' => $charge->id, 'method' => 'card']);
        return ['client_secret' => $intent->client_secret, 'charge' => $charge];
    }

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

        $intent = $this->client()->paymentIntents->retrieve(
            $paymentIntentId,
            [],
            $charge->stripe_account_id ? ['stripe_account' => $charge->stripe_account_id] : [],
        );

        if ($charge->status !== Charge::STATUS_SUCCESS && $charge->status !== Charge::STATUS_REFUNDED) {
            $charge->update([
                'status' => $intent->status === 'succeeded' ? Charge::STATUS_SUCCESS : Charge::STATUS_FAILED,
                'captured_at' => $intent->status === 'succeeded' ? now() : null,
            ]);
        }

        if ($charge->status === Charge::STATUS_SUCCESS && in_array($charge->type, [Charge::TYPE_DEPOSIT, Charge::TYPE_INCIDENTALS], true)) {
            // Whatever the current incidentals_charge is at the moment this
            // charge succeeds is now considered "billed" — the combined
            // pre-checkin charge includes incidentals as of that moment,
            // and a standalone incidentals charge settles it in full. Either
            // way, unbilledIncidentalsCents() should return 0 right after.
            $charge->booking->update([
                'incidentals_billed_cents' => (int) round(($charge->booking->effectiveIncidentalsCharge() ?? 0) * 100),
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

        if ($charge->status === Charge::STATUS_SUCCESS && $charge->type === Charge::TYPE_ACCOMMODATION) {
            app(DirectBookingService::class)->confirmCardPayment($charge->fresh('booking'));
        }

        if ($charge->type === Charge::TYPE_APPLICATION_FEE && $charge->leaseApplication) {
            $wasPaid = $charge->leaseApplication->fee_status === 'paid';
            $charge->leaseApplication->update([
                'fee_status' => $charge->status === Charge::STATUS_SUCCESS ? 'paid' : 'failed',
            ]);
            if ($charge->status === Charge::STATUS_SUCCESS && ! $wasPaid) {
                app(LeaseApplicationNotificationService::class)
                    ->feePaid($charge->leaseApplication->fresh());
            }
        }


        if ($charge->type === 'lease_installment' && $charge->lease_installment_id && $charge->status === Charge::STATUS_SUCCESS) {
            $installment = \App\Models\LeaseInstallment::query()->find($charge->lease_installment_id);
            if ($installment && $installment->status === \App\Models\LeaseInstallment::STATUS_PENDING) {
                $installment->update(['status' => \App\Models\LeaseInstallment::STATUS_PAID, 'paid_at' => now(), 'method' => 'card']);
                app(\App\Services\LeaseOfferService::class)->confirmIfReady($installment->offer);
            }
        }

        return $charge;
    }

    public function cancelPendingIntent(Charge $charge): void
    {
        if ($charge->status !== Charge::STATUS_PENDING || ! $charge->stripe_payment_intent_id) {
            return;
        }

        $this->client()->paymentIntents->cancel(
            $charge->stripe_payment_intent_id,
            [],
            $charge->stripe_account_id ? ['stripe_account' => $charge->stripe_account_id] : [],
        );
    }

    public function refund(Charge $charge, int $amountCents): Charge
    {
        $remaining = $charge->amount_cents - (int) $charge->refunded_cents;
        if (! $charge->stripe_account_id || ! $charge->stripe_payment_intent_id
            || ! in_array($charge->status, [Charge::STATUS_SUCCESS, Charge::STATUS_PARTIALLY_CAPTURED], true) || $amountCents < 1 || $amountCents > $remaining) {
            throw new \InvalidArgumentException('This charge cannot be refunded for the requested amount.');
        }

        \Stripe\Stripe::setMaxNetworkRetries(1);
        $this->client()->refunds->create(
            [
                'payment_intent' => $charge->stripe_payment_intent_id,
                'amount' => $amountCents,
                'reason' => 'requested_by_customer',
                'refund_application_fee' => true,
            ],
            ['stripe_account' => $charge->stripe_account_id],
        );

        $refunded = (int) $charge->refunded_cents + $amountCents;
        $charge->update([
            'refunded_cents' => $refunded,
            'status' => $refunded >= $charge->amount_cents
                ? Charge::STATUS_REFUNDED
                : Charge::STATUS_PARTIALLY_CAPTURED,
        ]);

        return $charge->fresh();
    }

    private function platformFeeCents(int $amountCents): int
    {
        $percent = trim((string) Setting::getValue('direct_booking_platform_fee_percent', '0'));
        if (! preg_match('/\A(\d{1,3})(?:\.(\d{1,2}))?\z/', $percent, $parts)) {
            return 0;
        }

        $basisPoints = min(10000, ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0'));

        return intdiv(($amountCents * $basisPoints) + 5000, 10000);
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
