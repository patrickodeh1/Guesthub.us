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
        $nextInstallment = $offer->installments->first(fn ($i) => $i->status === 'pending');
        if (in_array($offer->status, [LeaseOffer::STATUS_SIGNED, LeaseOffer::STATUS_CONFIRMED], true) && $offer->payment_method === 'card') {
            for ($attempt = 0; $attempt < 3 && $nextInstallment; $attempt++) {
                $intent = $payments->createLeaseOfferInstallmentIntent($offer, $nextInstallment);
                $nextInstallment->refresh();
                if ($nextInstallment->status === 'pending') {
                    $clientSecret = $intent['client_secret'];
                    break;
                }
                $offer->load(['installments']);
                $nextInstallment = $offer->installments->first(fn ($i) => $i->status === 'pending');
            }
        }
        return view('public.lease-offer', [
            'offer' => $offer->fresh(['application.listing', 'property.owner', 'installments', 'hostPaymentMethod', 'booking']),
            'clientSecret' => $clientSecret,
            'nextInstallment' => $nextInstallment,
            'publishableKey' => config('services.stripe.key'),
            'stripeAccountId' => $offer->property->owner?->stripe_account_id,
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
        $offer->installments()->where('status', 'pending')->update(['status' => 'waived']);\n        $offer->update(['status' => LeaseOffer::STATUS_DECLINED, 'declined_at' => now(), 'declined_reason' => $request->input('reason')]);
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
        abort_unless($charge && $charge->lease_installment_id, 404);
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
