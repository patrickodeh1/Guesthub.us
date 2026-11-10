<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\PropertyAvailability;
use App\Models\PropertyListing;
use Carbon\Carbon;

class AvailabilitySearch
{
    /**
     * Return a date-indexed range in two queries, without loading guest data.
     *
     * @return array<string, array{available: bool, night_available: bool, arrival_allowed: bool, departure_allowed: bool, minimum_nights: int, minimum_arrival_nights: int, minimum_through_nights: int, maximum_nights: ?int}>
     */
    public function calendar(PropertyListing $listing, string $from, string $through): array
    {
        $property = $listing->property;
        $timezone = $property?->timezone;
        if (! $timezone || ! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = config('app.timezone');
        }
        $start = Carbon::parse($from, $timezone)->startOfDay();
        $end = Carbon::parse($through, $timezone)->startOfDay();
        abort_if($end->lt($start) || $start->diffInDays($end) > 370, 422);

        $now = Carbon::now($timezone);
        $checkInTime = $property?->checkin_time ?: '16:00';
        if (! preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/', $checkInTime)) {
            $checkInTime = '16:00';
        }
        $cutoff = $listing->same_day_cutoff_time;
        if ($cutoff && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $cutoff)) {
            $cutoff .= ':00';
        }

        $propertyId = (int) $listing->property_id;
        $availability = PropertyAvailability::query()
            ->where('property_id', $propertyId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn (PropertyAvailability $day) => $day->date->toDateString());

        $bookedNights = [];
        Booking::query()
            ->where('property_id', $propertyId)
            ->holdingNights()
            ->whereDate('check_in_date', '<=', $end->toDateString())
            ->whereDate('check_out_date', '>', $start->toDateString())
            ->get(['check_in_date', 'check_out_date'])
            ->each(function (Booking $booking) use (&$bookedNights, $start, $end) {
                $first = max($start->toDateString(), $booking->check_in_date->toDateString());
                $last = min($end->toDateString(), $booking->check_out_date->toDateString());
                for ($date = Carbon::parse($first); $date->lt($last); $date->addDay()) {
                    $bookedNights[$date->toDateString()] = true;
                }
            });

        $days = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            /** @var PropertyAvailability|null $row */
            $row = $availability->get($key);
            $blockedByLedger = $row && (
                ! $row->is_available
                || in_array(strtolower((string) $row->status), ['blocked', 'closed', 'unavailable'], true)
                || $row->stop_sell === true
            );
            $nightAvailable = ! isset($bookedNights[$key]) && ! $blockedByLedger;
            $arrivalMoment = $date->copy()->setTimeFromTimeString($checkInTime);
            $arrivalAllowed = $row?->closed_to_arrival !== true
                && ! $arrivalMoment->lt($now->copy()->addHours((int) $listing->advance_notice_hours))
                && ! ($date->isSameDay($now)
                    && $cutoff
                    && $now->format('H:i:s') > $cutoff);
            $departureAllowed = $row?->closed_to_departure !== true;
            $minimumArrivalNights = (int) ($row?->min_stay_arrival ?: 0);
            $minimumThroughNights = (int) ($row?->min_stay_through ?: 0);
            $days[$key] = [
                'available' => $nightAvailable && $arrivalAllowed && $departureAllowed,
                'night_available' => $nightAvailable,
                'arrival_allowed' => $arrivalAllowed,
                'departure_allowed' => $departureAllowed,
                'minimum_nights' => max(
                    (int) ($listing->min_nights ?: 1),
                    $minimumArrivalNights,
                    $minimumThroughNights,
                ),
                'minimum_arrival_nights' => $minimumArrivalNights,
                'minimum_through_nights' => $minimumThroughNights,
                'maximum_nights' => $row?->max_stay ? (int) $row->max_stay : null,
            ];
        }

        return $days;
    }

    public static function rateToCents(?string $rate): ?int
    {
        if ($rate === null || ! preg_match('/\A(\d+)(?:\.(\d{1,2}))?\z/', $rate, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }
}
