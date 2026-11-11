<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\PropertyListing;
use App\Models\Setting;
use Carbon\Carbon;

class DirectBookingCancellationPolicy
{
    /** Refund percent (0-100) the listing's policy gives if cancelled right now. */
    public function suggestedPercent(Booking $booking): int
    {
        $listing = PropertyListing::query()->where('property_id', $booking->property_id)->first();
        if (! $listing) {
            return 0;
        }

        $timezone = $booking->property?->timezone;
        if (! $timezone || ! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = config('app.timezone');
        }
        $checkInTime = $booking->property?->checkin_time ?: '16:00';
        if (! preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/', $checkInTime)) {
            $checkInTime = '16:00';
        }
        $checkIn = Carbon::parse($booking->check_in_date->toDateString(), $timezone)->setTimeFromTimeString($checkInTime);

        return CancellationPolicy::refundPercent((string) $listing->cancellation_policy, $checkIn, Carbon::now($timezone));
    }

    /**
     * Cents to refund. $percentOverride (0-100) replaces the policy percentage.
     * The card processing fee is kept unless the admin setting says to refund it.
     */
    public function refundableCents(Booking $booking, Charge $charge, ?int $percentOverride = null): int
    {
        $percent = $percentOverride !== null ? max(0, min(100, $percentOverride)) : $this->suggestedPercent($booking);
        $keepFee = Setting::getValue('refund_processing_fee', '0') !== '1';
        $base = $charge->amount_cents - ($keepFee ? (int) $booking->processing_fee_cents : 0);
        $gross = intdiv((max(0, $base) * $percent) + 50, 100);

        return max(0, min($gross, $charge->amount_cents - (int) $charge->refunded_cents));
    }
}
