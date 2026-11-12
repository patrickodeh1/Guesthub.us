<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\LeaseOffer;
use App\Services\LeaseOfferService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;

class LeaseOfferController extends Controller
{
    public function show(int $application, string $applicationToken, string $offerToken, PaymentService $payments)
    {
        $offer = $this->findOffer($application, $applicationToken, $offerToken);
        $offer->load(['application.listing', 'property.owner', 'installments', 'hostPaymentMethod', 'booking']);
        $clientSecret = null;
        $initialPaymentPending = $offer->installments->where('required_at_signing', true)->contains(fn ($i) => $i->status === 'pending');
        $nextInstallment = $offer->status === LeaseOffer::STATUS_CONFIRMED
            ? $offer->installments->first(fn ($i) => ! $i->required_at_signing && $i->status === 'pending')
            : null;

        if ($offer->status === LeaseOffer::STATUS_SIGNED && $initialPaymentPending && $offer->accept_by?->isFuture() && $offer->payment_method === 'card') {
            $intent = $payments->createLeaseOfferInitialPaymentIntent($offer);
            $clientSecret = $intent['client_secret'];
        } elseif ($offer->status === LeaseOffer::STATUS_CONFIRMED && $nextInstallment && $offer->payment_method === 'card') {
            $intent = $payments->createLeaseOfferInstallmentIntent($offer, $nextInstallment);
            $clientSecret = $intent['client_secret'];
        }
        return view('public.lease-offer', [
            'offer' => $offer->fresh(['application.listing', 'property.owner', 'installments', 'hostPaymentMethod', 'booking']),
            'clientSecret' => $clientSecret,
            'nextInstallment' => $nextInstallment,
            'initialPaymentPending' => $initialPaymentPending,
            'publishableKey' => config('services.stripe.key'),
            'stripeAccountId' => $offer->property->owner?->stripe_account_id,
            'initialPaymentCharge' => Charge::query()->where('lease_offer_id', $offer->id)->where('type', Charge::TYPE_LEASE_INITIAL_PAYMENT)->latest()->first(),
        ]);
    }

    public function sign(Request $request, int $application, string $applicationToken, string $offerToken, LeaseOfferService $service)
    {
        $offer = $this->findOffer($application, $applicationToken, $offerToken);
        $data = $request->validate(['signature' => ['required', 'string', 'max:255'], 'accept_terms' => ['accepted']]);
        $service->signGuest($offer, $data['signature'], (string) $request->ip(), (string) $request->userAgent());
        return redirect()->route('public.lease-offer.show', [$application, $applicationToken, $offerToken])->with('status', 'Your signature has been recorded.');
    }

    public function decline(Request $request, int $application, string $applicationToken, string $offerToken, PaymentService $payments)
    {
        $offer = $this->findOffer($application, $applicationToken, $offerToken);
        abort_unless(in_array($offer->status, [LeaseOffer::STATUS_SENT, LeaseOffer::STATUS_SIGNED], true), 409);
        foreach (Charge::query()->where('lease_offer_id', $offer->id)->where('status', Charge::STATUS_PENDING)->get() as $charge) {
            try { $payments->cancelPendingIntent($charge); $charge->update(['status' => Charge::STATUS_FAILED]); } catch (\Throwable $e) { report($e); }
        }
        $offer->installments()->where('status', 'pending')->update(['status' => 'waived']);
        $offer->update(['status' => LeaseOffer::STATUS_DECLINED, 'declined_at' => now(), 'declined_reason' => $request->input('reason')]);
        $offer->booking?->update(['status' => 'declined', 'cancelled_at' => now(), 'hold_expires_at' => null]);
        if ($offer->booking) app(\App\Services\Pms\AvailabilityLedger::class)->syncBooking($offer->booking->fresh());
        return redirect()->route('public.lease-offer.show', [$application, $applicationToken, $offerToken])->with('status', 'You declined this rental offer. The dates have been released.');
    }

    public function confirmPayment(Request $request, int $application, string $applicationToken, string $offerToken, PaymentService $payments)
    {
        $offer = $this->findOffer($application, $applicationToken, $offerToken);
        $data = $request->validate(['payment_intent_id' => ['required', 'string', 'max:255']]);
        $charge = Charge::query()->where('lease_offer_id', $offer->id)
            ->where('stripe_payment_intent_id', $data['payment_intent_id'])->first();
        abort_unless($charge && in_array($charge->type, [Charge::TYPE_LEASE_INITIAL_PAYMENT, 'lease_installment'], true), 404);
        $charge = $payments->finalize($data['payment_intent_id']);
        if (! $charge || $charge->status !== Charge::STATUS_SUCCESS) {
            return response()->json(['ok' => false, 'error' => 'Payment could not be confirmed.'], 422);
        }
        return response()->json(['ok' => true, 'redirect' => route('public.lease-offer.show', [$application, $applicationToken, $offerToken])]);
    }

    private function findOffer(int $application, string $applicationToken, string $offerToken): LeaseOffer
    {
        $app = \App\Models\LeaseApplication::query()->findOrFail($application);
        abort_unless(filled($app->token) && hash_equals($app->token, $applicationToken), 404);
        return LeaseOffer::query()->where('lease_application_id', $app->id)->where('token', $offerToken)->firstOrFail();
    }
}
