<?php

namespace App\Services\Pms\PriceLabs;

use App\Models\Booking;
use App\Models\Property;
use App\Services\Pms\PriceLabsSync;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use RuntimeException;

class PriceLabsIapiSync
{
    public function __construct(
        private PriceLabsSync $sync,
        private PriceLabsIapiClient $client,
    ) {}

    public function listingId(Property $property): string
    {
        $environment = in_array(app()->environment(), ['production', 'prod'], true) ? 'prod' : 'test';

        return 'gh_'.$environment.'_'.$property->getKey();
    }

    public function registerListing(Property $property): array
    {
        $owner = $property->owner;
        if (! $owner || ! filter_var($owner->pricelabs_account_email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter the host email registered with PriceLabs first.');
        }

        $listing = $property->listing;
        $listingId = $property->pricelabs_iapi_listing_id ?: $this->listingId($property);
        $location = [
            'city' => (string) ($property->city ?: ''),
            'country' => strtoupper((string) ($property->country ?: 'USA')),
        ];
        if ($property->latitude !== null && $property->longitude !== null) {
            $location['latitude'] = (float) $property->latitude;
            $location['longitude'] = (float) $property->longitude;
        }

        $details = [
            'listing_id' => $listingId,
            'user_token' => $owner->pricelabs_account_email,
            'location' => $location,
            'name' => (string) ($listing?->public_title ?: $property->name),
            'status' => $property->active ? 'available' : 'unavailable',
            'number_of_bedrooms' => (float) ($listing?->bedrooms ?? $property->beds ?? 0),
            'property_type' => 'short_term_rental',
        ];

        $response = $this->client->listings('POST', ['listings' => [$details]]);
        $successes = $response['successes'] ?? [];
        $errors = $response['errors'] ?? [];
        if (! in_array($listingId, $successes, true)) {
            $message = $errors[0]['error'] ?? 'PriceLabs did not confirm the listing.';
            $property->forceFill(['pricelabs_iapi_last_error' => mb_substr((string) $message, 0, 5000)])->save();
            throw new RuntimeException((string) $message);
        }

        $property->forceFill([
            'pricelabs_iapi_listing_id' => $listingId,
            'pricelabs_mode' => 'pms',
            'pricing_provider' => 'pricelabs',
            'pricelabs_listing_id' => null,
            'pricelabs_pms' => null,
            'wheelhouse_listing_id' => null,
            'wheelhouse_channel' => null,
            'rate_source' => $property->channex_rate_plan_id ? 'guesthub' : $property->rate_source,
            'pricelabs_iapi_listing_pushed_at' => now(),
            'pricelabs_iapi_last_error' => null,
        ])->save();

        return $response;
    }

    public function pushCalendar(Property $property, ?string $startDate = null, ?string $endDate = null): array
    {
        $listingId = (string) $property->pricelabs_iapi_listing_id;
        if ($listingId === '') {
            throw new RuntimeException('Register the listing with PriceLabs before sending its calendar.');
        }

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->startOfDay() : $start->copy()->addDays(729);
        if ($end->lt($start) || $start->diffInDays($end) > 1095) {
            throw new InvalidArgumentException('The PriceLabs calendar date range is invalid or too large.');
        }

        $listing = $property->listing;
        $baseRate = $listing?->base_rate_cents !== null ? ((float) $listing->base_rate_cents / 100) : null;
        $availability = $property->availabilities()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($row) => $row->date->format('Y-m-d'));

        $bookings = Booking::query()
            ->where('property_id', $property->id)
            ->holdingNights()
            ->whereDate('check_in_date', '<=', $end->toDateString())
            ->whereDate('check_out_date', '>', $start->toDateString())
            ->get(['id', 'check_in_date', 'check_out_date']);

        $bookingCounts = [];
        foreach ($bookings as $booking) {
            $night = $booking->check_in_date->copy()->startOfDay()->max($start);
            $last = $booking->check_out_date->copy()->startOfDay()->min($end->copy()->addDay());
            while ($night->lt($last)) {
                $key = $night->toDateString();
                $bookingCounts[$key] = ($bookingCounts[$key] ?? 0) + 1;
                $night->addDay();
            }
        }

        $data = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $row = $availability->get($date);
            $rate = $row?->rate !== null ? (float) $row->rate : $baseRate;
            if ($rate === null || $rate <= 0) {
                $message = 'A nightly rate is missing for '.$date.'. Set a base listing rate or a calendar rate before the initial PriceLabs calendar push.';
                $property->forceFill(['pricelabs_iapi_last_error' => $message])->save();
                throw new RuntimeException($message);
            }

            $booked = min(1, (int) ($bookingCounts[$date] ?? 0));
            $blocked = $booked === 0 && $row && ! $row->is_available ? 1 : 0;
            $settings = [];
            if ($row?->min_stay_arrival !== null) {
                $settings['min_stay'] = (int) $row->min_stay_arrival;
            }
            if ($row) {
                $settings['check_in'] = ! (bool) $row->closed_to_arrival;
                $settings['check_out'] = ! (bool) $row->closed_to_departure;
            }

            $item = [
                'date' => $date,
                'end_date' => $date,
                'price' => (int) round($rate),
                'available_units' => max(0, 1 - $booked - $blocked),
                'booked_units' => $booked,
                'blocked_units' => $blocked,
            ];
            if ($settings !== []) {
                $item['settings'] = $settings;
            }
            $data[] = $item;
        }

        try {
            $response = $this->client->calendar('POST', ['calendars' => [[
                'listing_id' => $listingId,
                'currency' => 'USD',
                'data' => $data,
            ]]]);
        } catch (\\Throwable $exception) {
            $property->forceFill(['pricelabs_iapi_last_error' => mb_substr($exception->getMessage(), 0, 5000)])->save();
            throw $exception;
        }
        if (! in_array($listingId, $response['success'] ?? [], true)) {
            $message = $response['failure'][0]['error'] ?? 'PriceLabs did not confirm the calendar.';
            $property->forceFill(['pricelabs_iapi_last_error' => mb_substr((string) $message, 0, 5000)])->save();
            throw new RuntimeException((string) $message);
        }

        $property->forceFill([
            'pricelabs_iapi_calendar_pushed_at' => now(),
            'pricelabs_iapi_last_error' => null,
        ])->save();

        return $response;
    }

    public function pushCalendarForListingId(string $listingId, ?string $startDate = null, ?string $endDate = null): array
    {
        $property = Property::query()
            ->where('pricelabs_iapi_listing_id', $listingId)
            ->where('pricelabs_mode', 'pms')
            ->where('pricing_provider', 'pricelabs')
            ->first();

        if (! $property) {
            throw (new ModelNotFoundException())->setModel(Property::class, [$listingId]);
        }

        return $this->pushCalendar($property, $startDate, $endDate);
    }

    public function pushReservations(Property $property): array
    {
        $listingId = (string) $property->pricelabs_iapi_listing_id;
        if ($listingId === '') {
            throw new RuntimeException('Register the listing with PriceLabs before sending reservations.');
        }

        $bookings = Booking::query()
            ->where('property_id', $property->id)
            ->whereDate('check_out_date', '>=', '2020-01-01')
            ->whereNotNull('check_in_date')
            ->whereNotNull('check_out_date')
            ->get();

        $reservations = [];
        foreach ($bookings as $booking) {
            $start = $booking->check_in_date->toDateString();
            $end = $booking->check_out_date->toDateString();
            $nights = max(1, (int) $booking->check_in_date->diffInDays($booking->check_out_date));
            $cancelled = ! is_null($booking->cancelled_at) || in_array(strtolower((string) $booking->status), ['cancelled', 'canceled'], true);
            $totalCents = (int) ($booking->total_cents ?? 0);
            $rentalCents = (int) ($booking->accommodation_cents ?? 0);
            $taxCents = (int) ($booking->tax_cents ?? 0);
            $feesCents = max(0, $totalCents - $rentalCents - $taxCents);
            $reservationId = 'gh_'.$property->id.'_booking_'.$booking->id;

            $reservations[] = [
                'reservation_id' => $reservationId,
                'start_date' => $start,
                'end_date' => $end,
                'booked_time' => ($booking->created_at ?: now())->format('Y-m-d H:i:s'),
                'total_days' => $nights,
                'total_cost' => round($totalCents / 100, 2),
                'total_fees' => round($feesCents / 100, 2),
                'total_taxes' => round($taxCents / 100, 2),
                'rental_revenue' => round($rentalCents / 100, 2),
                'currency' => 'USD',
                'status' => $cancelled ? 'canceled' : (in_array(strtolower((string) $booking->status), ['pending', 'pending_payment', 'unconfirmed'], true) ? 'unconfirmed' : 'booked'),
            ];
            if ($cancelled) {
                $reservations[array_key_last($reservations)]['cancel_time'] = ($booking->cancelled_at ?: now())->toDateString();
            }
            if (filled($booking->booking_platform)) {
                $reservations[array_key_last($reservations)]['booking_source'] = strtolower((string) $booking->booking_platform);
            }
        }

        // Explicit calendar blocks are represented as zero-cost blocked reservations
        // so PriceLabs Portfolio Analytics can distinguish owner/maintenance blocks.
        $blockedRows = $property->availabilities()
            ->where('status', 'blocked')
            ->whereDate('date', '>=', '2020-01-01')
            ->get();

        foreach ($blockedRows as $blocked) {
            $date = $blocked->date->toDateString();
            $reservations[] = [
                'reservation_id' => 'gh_'.$property->id.'_block_'.$date,
                'start_date' => $date,
                'end_date' => Carbon::parse($date)->addDay()->toDateString(),
                'booked_time' => ($blocked->created_at ?: now())->format('Y-m-d H:i:s'),
                'total_days' => 1,
                'total_cost' => 0,
                'total_fees' => 0,
                'total_taxes' => 0,
                'rental_revenue' => 0,
                'currency' => 'USD',
                'status' => 'blocked',
            ];
        }

        try {
            $response = $this->client->reservations('POST', ['reservations' => [[
                'listing_id' => $listingId,
                'data' => $reservations,
            ]]]);
        } catch (\\Throwable $exception) {
            $property->forceFill(['pricelabs_iapi_last_error' => mb_substr($exception->getMessage(), 0, 5000)])->save();
            throw $exception;
        }
        if (! empty($response['failure'])) {
            $message = $response['failure'][0]['error'] ?? 'One or more reservations failed.';
            $property->forceFill(['pricelabs_iapi_last_error' => mb_substr((string) $message, 0, 5000)])->save();
            throw new RuntimeException((string) $message);
        }

        $property->forceFill(['pricelabs_iapi_last_error' => null])->save();

        return $response;
    }

    public function applyForListingId(string $listingId, array $rates): array
    {
        $property = Property::query()
            ->where('pricelabs_iapi_listing_id', $listingId)
            ->where('pricelabs_mode', 'pms')
            ->where('pricing_provider', 'pricelabs')
            ->first();

        if (! $property) {
            throw (new ModelNotFoundException())->setModel(Property::class, [$listingId]);
        }

        $normalized = [];
        foreach ($rates as $day) {
            if (! is_array($day)
                || ! isset($day['date'], $day['rate'])
                || ! is_numeric($day['rate'])
                || ! $this->validDate((string) $day['date'])) {
                throw new InvalidArgumentException('PriceLabs IAPI rate rows must contain a valid date and numeric rate.');
            }

            $rate = round((float) $day['rate'], 2);
            if ($rate <= 0 || ! is_finite($rate)) {
                continue;
            }
            $minimumStay = $day['min_stay'] ?? null;
            if ($minimumStay !== null && (! is_numeric($minimumStay) || (int) $minimumStay < 1)) {
                throw new InvalidArgumentException('PriceLabs IAPI min_stay must be a positive integer or null.');
            }

            $normalized[] = [
                'date' => (string) $day['date'],
                'rate' => $rate,
                'min_stay' => $minimumStay === null ? null : (int) $minimumStay,
            ];
        }

        // A host's manually entered nightly values remain protected from provider updates.
        $dates = array_values(array_unique(array_column($normalized, 'date')));
        $manualDates = $property->availabilities()
            ->whereIn('date', $dates)
            ->where('rates_source', 'manual')
            ->pluck('date')
            ->map(fn ($date) => substr((string) $date, 0, 10))
            ->all();

        $groups = [];
        foreach ($normalized as $day) {
            if (in_array($day['date'], $manualDates, true)) {
                continue;
            }
            $groups[$day['rate'].'|'.($day['min_stay'] ?? '')][] = $day['date'];
        }

        $result = $this->sync->apply($property, [
            'status' => 'ready',
            'changed' => array_sum(array_map('count', $groups)),
            'message' => 'PriceLabs IAPI rates received.',
            'provider' => 'pricelabs',
            'refreshed_at' => now()->toIso8601String(),
            'groups' => $groups,
        ]);

        $property->forceFill([
            'pricelabs_iapi_last_price_received_at' => now(),
            'pricelabs_iapi_last_error' => null,
        ])->save();

        return $result;
    }

    private function validDate(string $date): bool
    {
        if (! preg_match('/\\A(\\d{4})-(\\d{2})-(\\d{2})\\z/', $date, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
