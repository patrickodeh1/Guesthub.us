<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Charge;
use App\Models\PropertyListing;
use Carbon\Carbon;

class DirectBookingCancellationPolicy
{
    public function refundableCents(Booking $booking, Charge $charge): int
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
        $hoursUntilArrival = Carbon::now($timezone)->diffInHours(
            Carbon::parse($booking->check_in_date->toDateString(), $timezone)->setTimeFromTimeString($checkInTime),
            false,
        );
        $refundPercent = match ($listing->cancellation_policy) {
            'flexible' => $hoursUntilArrival >= 24 ? 100 : 0,
            'strict' => $hoursUntilArrival >= 168 ? 50 : 0,
            default => $hoursUntilArrival >= 120 ? 100 : 0,
        };
        $grossRefund = intdiv(($charge->amount_cents * $refundPercent) + 50, 100);

        return max(0, $grossRefund - (int) $charge->refunded_cents);
    }
}
