<?php

namespace App\\Services\\Pms\\PriceLabs;

use App\\Models\\Property;
use App\\Services\\Pms\\PriceLabsSync;
use Illuminate\\Database\\Eloquent\\ModelNotFoundException;
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

    private function validDate(string $date): bool
    {
        if (! preg_match('/\\A(\\d{4})-(\\d{2})-(\\d{2})\\z/', $date, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }
}
