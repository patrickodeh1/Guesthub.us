<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\Feature;
use App\Models\PropertyListing;
use App\Services\DirectBookingService;
use App\Services\FeatureGate;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DirectBookingController extends Controller
{
    public function checkout(string $listingSlug)
    {
        $listing = $this->publishedListing($listingSlug);
        $owner = $listing->property->owner;
        $canCard = filled($owner?->stripe_account_id) && $owner?->stripe_charges_enabled && app(PaymentService::class)->isConfigured();
        $manualMethods = $owner && FeatureGate::enabled($owner, 'manual_payments')
            ? $owner->hostPaymentMethods->where('enabled', true)->values()
            : collect();
        abort_unless($canCard || $manualMethods->isNotEmpty(), 404);

        return view('public.checkout', [
            'listing' => $listing,
            'canCard' => $canCard,
            'manualMethods' => $manualMethods,
            'minimumDate' => now($this->timezone($listing->property->timezone))->toDateString(),
        ]);
    }

    public function start(Request $request, string $listingSlug, DirectBookingService $bookings)
    {
        $data = $request->validate([
            'website' => ['nullable', 'size:0'],
            'guest_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:100'],
            'payment_method' => ['required', 'in:card,manual'],
            'host_payment_method_id' => ['required_if:payment_method,manual', 'nullable', 'integer'],
            'terms' => ['accepted'],
        ]);
        if ($request->filled('website')) {
            throw ValidationException::withMessages(['checkout' => 'Unable to process this request.']);
        }

        $booking = $bookings->start($this->publishedListing($listingSlug), $data);

        return redirect()->route('public.checkout.pay', [$booking->booking_id, $booking->token]);
    }

    public function pay(string $bookingId, string $token, PaymentService $payments, DirectBookingService $bookings)
    {
        $booking = $this->guestBooking($bookingId, $token)->load(['property.owner', 'hostPaymentMethod', 'charges']);
        $clientSecret = null;
        if ($booking->status === Booking::STATUS_PENDING_PAYMENT
            && $booking->hold_expires_at?->isPast()) {
            $bookings->expireHold($booking);
            $booking->refresh()->load(['property.owner', 'hostPaymentMethod', 'charges']);
        }
        if ($booking->payment_method === 'card' && $booking->status === Booking::STATUS_PENDING_PAYMENT
            && (! $booking->direct_booking_request || $booking->direct_booking_approved_at)) {
            abort_unless($payments->isConfigured(), 503, 'Card payment is temporarily unavailable.');
            $owner = $booking->property->owner;
            abort_unless(filled($owner?->stripe_account_id) && $owner?->stripe_charges_enabled, 422, 'Card payment is not available.');
            $payment = $payments->createDirectBookingIntent($booking, $owner->stripe_account_id);
            $clientSecret = $payment['client_secret'];
            $booking->refresh()->load(['property.owner', 'hostPaymentMethod', 'charges']);
            if ($booking->payment_confirmed_at) {
                $clientSecret = null;
            }
        }

        return view('public.checkout-status', [
            'booking' => $booking,
            'clientSecret' => $clientSecret,
            'publishableKey' => config('services.stripe.key'),
            'stripeAccountId' => $booking->property->owner?->stripe_account_id,
        ]);
    }

    public function confirm(Request $request, string $bookingId, string $token, PaymentService $payments)
    {
        $booking = $this->guestBooking($bookingId, $token);
        $data = $request->validate(['payment_intent_id' => ['required', 'string', 'max:255']]);
        $charge = $booking->charges()
            ->where('type', Charge::TYPE_ACCOMMODATION)
            ->where('stripe_payment_intent_id', $data['payment_intent_id'])
            ->first();
        if (! $charge) {
            return response()->json(['ok' => false, 'error' => 'Payment not found.'], 404);
        }

        $charge = $payments->finalize($data['payment_intent_id']);
        if (! $charge || $charge->status !== Charge::STATUS_SUCCESS) {
            return response()->json(['ok' => false, 'error' => 'Payment could not be confirmed.'], 422);
        }

        return response()->json(['ok' => true, 'redirect' => route('guest.show', [$booking->booking_id, $booking->token])]);
    }

    public function cancel(Request $request, string $bookingId, string $token, DirectBookingService $bookings)
    {
        abort(403, 'Cancellations are handled by the host. Please contact them directly.');

        // Guests cancel through guest services; staff cancel from the admin guest list.
        abort(404);

        $booking = $this->guestBooking($bookingId, $token);
        $refund = $bookings->cancel($booking, true);

        return redirect()->route('public.checkout.pay', [$booking->booking_id, $booking->token])
            ->with('status', $refund > 0
                ? 'Your booking was cancelled and a refund was initiated.'
                : 'Your booking was cancelled.');
    }

    private function publishedListing(string $slug): PropertyListing
    {
        $listing = PropertyListing::query()
            ->where('listing_slug', $slug)
            ->whereNotNull('published_at')
            ->with(['property.owner.hostPaymentMethods'])
            ->firstOrFail();
        abort_unless($listing->property?->active && $listing->property?->owner
            && FeatureGate::enabled($listing->property->owner, 'direct_booking_site'), 404);
        abort_unless(Feature::query()->where('key', 'direct_booking_site')->where('active', true)->exists(), 404);

        return $listing;
    }

    private function guestBooking(string $bookingId, string $token): Booking
    {
        return Booking::query()
            ->where('booking_id', $bookingId)
            ->where('token', $token)
            ->where('source', 'direct')
            ->firstOrFail();
    }

    private function timezone(?string $timezone): string
    {
        return $timezone && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : config('app.timezone');
    }
}
