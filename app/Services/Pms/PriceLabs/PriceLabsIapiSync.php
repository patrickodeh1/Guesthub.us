<?php

namespace App\Services\Pms\PriceLabs;

use App\Models\Property;
use App\Services\Pms\PriceLabsSync;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class PriceLabsIapiSync
{
    public function __construct(private PriceLabsSync $sync) {}

    public function listingId(Property $property): string
    {
        $environment = in_array(app()->environment(), ['production', 'prod'], true) ? 'prod' : 'test';

        return 'gh-'.$environment.'-'.$property->getKey();
    }

    /**
     * Apply already-normalized IAPI rate rows through the same ledger path used
     * by the legacy PriceLabs source. The inbound Swagger mapper must convert
     * its exact payload fields to this internal shape before calling this.
     *
     * @param array<int,array{date:string,rate:int|float|string,min_stay?:int|null}> $rates
     */
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

        $groups = [];
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
            $minimumStay = $minimumStay === null ? null : (int) $minimumStay;
            $groups[$rate.'|'.($minimumStay ?? '')][] = (string) $day['date'];
        }

        $changed = array_sum(array_map('count', $groups));
        $result = $this->sync->apply($property, [
            'status' => 'ready',
            'changed' => $changed,
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

    /**
     * Register/update one GuestHub property in PriceLabs.
     * The PriceLabs account email belongs to the property's owner, never to
     * GuestHub's shared integration account.
     */
    public function pushListing(Property $property, PriceLabsIapiClient $client): array
    {
        $property->loadMissing(['owner', 'listing']);
        $email = trim((string) $property->owner?->pricelabs_account_email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Add the host email registered with PriceLabs before sending this listing.');
        }

        $listingId = $property->pricelabs_iapi_listing_id ?: $this->listingId($property);
        $property->forceFill(['pricelabs_iapi_listing_id' => $listingId])->save();

        $payload = [
            'listing_id' => $listingId,
            'user_token' => $email,
            'listing_name' => (string) $property->name,
        ];

        if ($property->fullAddress() !== '') {
            $payload['address'] = $property->fullAddress();
        }
        if ($property->listing?->bedrooms !== null) {
            $payload['bedrooms'] = (int) $property->listing->bedrooms;
        }
        if ($property->listing?->max_guests !== null) {
            $payload['max_guests'] = (int) $property->listing->max_guests;
        }

        $response = $client->listings('POST', $payload);
        $property->forceFill([
            'pricelabs_iapi_listing_pushed_at' => now(),
            'pricelabs_iapi_last_error' => null,
        ])->save();

        return $response;
    }

    /**
     * Send one listing's daily rates and availability. Initial registration
     * always sends 730 dates; subsequent calls may send a narrower range.
     */
    public function pushCalendar(
        Property $property,
        PriceLabsIapiClient $client,
        ?string $from = null,
        ?string $to = null,
    ): array {
        $property->loadMissing('listing');
        $start = $from ? \\Carbon\\Carbon::parse($from)->startOfDay() : now()->startOfDay();
        $end = $to
            ? \\Carbon\\Carbon::parse($to)->startOfDay()
            : $start->copy()->addDays(729);

        if ($end->lt($start) || $start->diffInDays($end) > 1095) {
            throw new InvalidArgumentException('PriceLabs calendar range must be between 1 and 1096 days.');
        }

        $rows = $property->availabilities()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()->keyBy(fn ($row) => $row->date->format('Y-m-d'));

        $bookings = $property->bookings()
            ->holdingNights()
            ->whereDate('check_in_date', '<=', $end->toDateString())
            ->whereDate('check_out_date', '>', $start->toDateString())
            ->get(['check_in_date', 'check_out_date']);

        $bookingDates = [];
        foreach ($bookings as $booking) {
            $night = $booking->check_in_date->copy()->startOfDay()->max($start)->copy();
            $last = $booking->check_out_date->copy()->startOfDay()->min($end->copy()->addDay());
            for (; $night->lt($last); $night->addDay()) {
                $bookingDates[$night->toDateString()] = true;
            }
        }

        $baseRate = $property->listing?->base_rate_cents !== null
            ? round(((int) $property->listing->base_rate_cents) / 100, 2)
            : null;
        $calendarData = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $row = $rows->get($key);
            $price = $row?->rate !== null ? (float) $row->rate : $baseRate;

            if ($price === null || $price <= 0) {
                throw new InvalidArgumentException('A nightly rate is missing for '.$key.'. Add a base listing rate or fill the availability calendar before enabling PriceLabs.');
            }

            $calendarData[] = [
                'date' => $key,
                'price' => round($price, 2),
                'availability' => (bool) ($row?->is_available ?? ! isset($bookingDates[$key])),
                'min_stay' => (int) ($row?->min_stay_arrival ?: $property->listing?->min_nights ?: 1),
            ];
        }

        $response = $client->calendar('POST', [
            'listing_id' => $property->pricelabs_iapi_listing_id ?: $this->listingId($property),
            'calendar_data' => $calendarData,
        ]);

        $property->forceFill([
            'pricelabs_iapi_calendar_pushed_at' => now(),
            'pricelabs_iapi_last_error' => null,
        ])->save();

        return $response;
    }

    /**
     * Publish a booking create/update/cancellation for PriceLabs Portfolio
     * Analytics, then refresh the affected calendar range.
     */
    public function pushReservation(\\App\\Models\\Booking $booking, PriceLabsIapiClient $client): array
    {
        $property = $booking->property()->with(['listing'])->first();
        if (! $property || $property->pricelabs_mode !== 'pms'
            || $property->pricing_provider !== 'pricelabs'
            || ! $property->pricelabs_iapi_listing_id) {
            return ['skipped' => true];
        }

        $cancelled = in_array(strtolower((string) $booking->status), ['cancelled', 'canceled', 'declined', 'rejected'], true)
            || $booking->cancelled_at !== null;
        $payload = [
            'listing_id' => $property->pricelabs_iapi_listing_id,
            'reservation_id' => (string) ($booking->booking_id ?: $booking->id),
            'check_in' => $booking->check_in_date?->toDateString(),
            'check_out' => $booking->check_out_date?->toDateString(),
            'status' => $cancelled ? 'CANCELLED' : 'BOOKED',
            'total_cost' => round(((int) ($booking->total_cents ?? 0)) / 100, 2),
            'rental_revenue' => round(((int) ($booking->accommodation_cents ?? 0)) / 100, 2),
            'currency' => strtoupper((string) config('app.currency', 'USD')),
        ];

        $response = $client->reservations('POST', ['reservations' => [$payload]]);
        $from = $booking->check_in_date?->toDateString();
        $to = $booking->check_out_date?->copy()->subDay()->toDateString();
        if ($from && $to && $to >= $from) {
            $this->pushCalendar($property, $client, $from, $to);
        }

        return $response;
    }

    private function validDate(string $date): bool
    {
        if (! preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
