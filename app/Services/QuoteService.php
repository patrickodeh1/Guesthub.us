<?php

namespace App\Services;

use App\Models\PropertyAvailability;
use App\Models\PropertyListing;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function __construct(private readonly AvailabilitySearch $availabilitySearch) {}

    /**
     * @return array{currency: string, nights: int, line_items: array<int, array{label: string, amount_cents: int, date?: string}>, subtotal_cents: int, total_cents: int}
     */
    public function quote(
        PropertyListing $listing,
        string $checkIn,
        string $checkOut,
        int $guests,
        string $paymentMethod = 'card',
    ): array {
        $property = $listing->property;
        $timezone = $this->timezone($property?->timezone);
        $arrival = Carbon::parse($checkIn, $timezone)->startOfDay();
        $departure = Carbon::parse($checkOut, $timezone)->startOfDay();
        $nights = (int) $arrival->diffInDays($departure);

        if ($departure->lte($arrival) || $nights > 365) {
            $this->invalid('check_out', 'Choose a valid stay of 1 to 365 nights.');
        }
        if ($arrival->lt(Carbon::now($timezone)->startOfDay())) {
            $this->invalid('check_in', 'Choose today or a future arrival date.');
        }
        if ($guests < 1 || ! $listing->max_guests || $guests > $listing->max_guests) {
            $this->invalid('guests', 'The guest count is outside this listing’s limit.');
        }
        if (! in_array($paymentMethod, ['card', 'manual'], true)) {
            $this->invalid('payment_method', 'Choose a supported payment method.');
        }

        $now = Carbon::now($timezone);
        $cutoff = $listing->same_day_cutoff_time;
        if ($cutoff && preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $cutoff)) {
            $cutoff .= ':00';
        }
        if ($arrival->isSameDay($now) && $listing->same_day_cutoff_time
            && $cutoff && $now->format('H:i:s') > $cutoff) {
            $this->invalid('check_in', 'Same-day arrivals are no longer available.');
        }

        $checkInTime = $property?->checkin_time ?: '16:00';
        if (! preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?\z/', $checkInTime)) {
            $checkInTime = '16:00';
        }
        $arrivalMoment = $arrival->copy()->setTimeFromTimeString($checkInTime);
        if ($arrivalMoment->lt($now->copy()->addHours((int) $listing->advance_notice_hours))) {
            $this->invalid('check_in', 'This arrival does not meet the advance-notice requirement.');
        }

        $calendar = $this->availabilitySearch->calendar($listing, $arrival->toDateString(), $departure->toDateString());
        $arrivalRules = $calendar[$arrival->toDateString()];
        $minimumNights = max(
            (int) ($listing->min_nights ?: 1),
            $arrivalRules['minimum_arrival_nights'],
        );
        $maximumNights = null;
        for ($date = $arrival->copy(); $date->lt($departure); $date->addDay()) {
            $key = $date->toDateString();
            $day = $calendar[$key];
            if (! $day['night_available']) {
                $this->invalid('check_in', 'One or more selected dates are unavailable.');
            }
            if ($day['minimum_through_nights']) {
                $minimumNights = max($minimumNights, $day['minimum_through_nights']);
            }
            if ($day['maximum_nights']) {
                $maximumNights = min($maximumNights ?? $day['maximum_nights'], $day['maximum_nights']);
            }
        }
        if (! $arrivalRules['arrival_allowed']) {
            $this->invalid('check_in', 'Arrivals are not available on the selected date.');
        }
        if (! $calendar[$departure->toDateString()]['departure_allowed']) {
            $this->invalid('check_out', 'Departures are not available on the selected date.');
        }
        if ($nights < $minimumNights) {
            $this->invalid('check_out', "This listing requires at least {$minimumNights} nights.");
        }
        if ($maximumNights !== null && $nights > $maximumNights) {
            $this->invalid('check_out', "This listing allows a maximum of {$maximumNights} nights for these dates.");
        }
        if (BookingConflictService::find((int) $listing->property_id, $arrival, $departure)) {
            $this->invalid('check_in', 'One or more selected dates are no longer available.');
        }

        $rateRows = PropertyAvailability::query()
            ->where('property_id', $listing->property_id)
            ->whereBetween('date', [$arrival->toDateString(), $departure->copy()->subDay()->toDateString()])
            ->get(['date', 'rate'])
            ->keyBy(fn (PropertyAvailability $row) => $row->date->toDateString());
        $items = [];
        $nightlySubtotal = 0;
        for ($date = $arrival->copy(); $date->lt($departure); $date->addDay()) {
            $key = $date->toDateString();
            $rate = AvailabilitySearch::rateToCents($rateRows->get($key)?->rate);
            $rate ??= (int) $listing->base_rate_cents;
            $nightlySubtotal += $rate;
            $items[] = [
                'label' => $date->format('M j').' nightly rate',
                'amount_cents' => $rate,
                'date' => $key,
            ];
        }

        $cleaningFee = (int) ($listing->cleaning_fee_cents ?? 0);
        if ($cleaningFee > 0) {
            $items[] = ['label' => 'Cleaning fee', 'amount_cents' => $cleaningFee];
        }

        $taxableCents = $nightlySubtotal + $cleaningFee;
        $taxPercent = $this->percentBasisPoints((string) ($listing->tax_percent ?? '0'));
        $taxCents = $this->percentageOf($taxableCents, $taxPercent);
        if ($taxCents > 0) {
            $items[] = ['label' => 'Estimated tax', 'amount_cents' => $taxCents];
        }

        $processingFee = 0;
        if ($paymentMethod === 'card') {
            $feePercent = $this->percentBasisPoints((string) Setting::getValue('processing_fee_percent', 0));
            $processingFee = $this->percentageOf($taxableCents + $taxCents, $feePercent);
            if ($processingFee > 0) {
                $items[] = ['label' => 'Card processing fee', 'amount_cents' => $processingFee];
            }
        }

        return [
            'currency' => 'USD',
            'nights' => $nights,
            'line_items' => $items,
            'subtotal_cents' => $nightlySubtotal,
            'total_cents' => $nightlySubtotal + $cleaningFee + $taxCents + $processingFee,
        ];
    }

    private function timezone(?string $timezone): string
    {
        return $timezone && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : config('app.timezone');
    }

    private function percentBasisPoints(string $value): int
    {
        if (! preg_match('/\A(\d{1,3})(?:\.(\d{1,2}))?\z/', trim($value), $matches)) {
            return 0;
        }

        return min(10000, ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0'));
    }

    private function percentageOf(int $amountCents, int $basisPoints): int
    {
        return intdiv(($amountCents * $basisPoints) + 5000, 10000);
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
