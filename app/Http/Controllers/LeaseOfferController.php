<?php

namespace App\Http\Controllers;

use App\Models\LeaseApplication;
use App\Models\LeaseInstallment;
use App\Models\LeaseOffer;
use App\Services\LeaseOfferService;
use Illuminate\Http\Request;

class LeaseOfferController extends Controller
{
    public function show(int $application, string $token)
    {
        $offer = LeaseOffer::query()->whereHas('application', fn ($q) => $q->whereKey($application))
            ->where('token', $token)->with(['application.listing', 'property', 'installments', 'hostPaymentMethod'])->firstOrFail();
        return view('public.lease-offer', compact('offer'));
    }

    public function sign(Request $request, int $application, string $token, LeaseOfferService $service)
    {
        $offer = LeaseOffer::query()->whereHas('application', fn ($q) => $q->whereKey($application))
            ->where('token', $token)->firstOrFail();
        $data = $request->validate(['signature' => ['required', 'string', 'max:255'], 'accept_terms' => ['accepted']]);
        $service->signGuest($offer, $data['signature'], (string) $request->ip(), (string) $request->userAgent());
        return redirect()->route('public.lease-offer.show', [$application, $token])->with('status', 'Your signature has been recorded.');
    }

    public function decline(Request $request, int $application, string $token)
    {
        $offer = LeaseOffer::query()->whereHas('application', fn ($q) => $q->whereKey($application))
            ->where('token', $token)->firstOrFail();
        abort_unless(in_array($offer->status, [LeaseOffer::STATUS_SENT, LeaseOffer::STATUS_SIGNED], true), 409);
        $offer->update(['status' => LeaseOffer::STATUS_DECLINED, 'declined_at' => now(), 'declined_reason' => $request->input('reason')]);
        $offer->booking?->update(['status' => 'declined', 'cancelled_at' => now(), 'hold_expires_at' => null]);
        if ($offer->booking) app(\App\Services\Pms\AvailabilityLedger::class)->syncBooking($offer->booking->fresh());
        return redirect()->route('public.lease-offer.show', [$application, $token])->with('status', 'You declined this rental offer. The dates have been released.');
    }
}
