<?php

namespace App\Services\Pms;

use App\Models\Property;

/**
 * Fetches provider recommendations, diffs them against the ledger, and
 * applies only changed dates through the shared availability ledger.
 */
class PriceLabsSync
{
    public function __construct(
        private AvailabilityLedger $ledger,
        private PriceLabsRateSource $priceLabs,
        private WheelhouseRateSource $wheelhouse,
    ) {}

    /**
     * @return array{status:string,changed:int,message:string,provider?:string,groups?:array}
     */
    public function sync(Property $property, bool $force = false): array
    {
        return $this->apply($property, $this->fetch($property, $force));
    }

    /**
     * Fetch provider rates and diff them against existing ledger rows.
     *
     * @return array{status:string,changed:int,message:string,provider?:string,groups?:array,refreshed_at?:?string}
     */
    public function fetch(Property $property, bool $force = false): array
    {
        $source = match ($property->pricing_provider) {
            'pricelabs' => $this->priceLabs,
            'wheelhouse' => $this->wheelhouse,
            default => null,
        };

        if (! $source) {
            return $this->out('skipped', 0, 'No pricing provider is selected.');
        }

        $usesChannexOutbox = $property->channex_property_id
            || $property->channex_room_type_id
            || $property->channex_rate_plan_id;
        if ($usesChannexOutbox && ! $property->channex_rate_plan_id) {
            return $this->out('skipped', 0, 'Choose the Channex rate plan first.');
        }
        if ($property->channex_rate_plan_id && $property->rate_source !== 'guesthub') {
            $property->forceFill(['rate_source' => 'guesthub'])->save();
        }

        $result = $source->fetch($property, $force);
        if ($result['status'] !== 'ok') {
            if ($result['status'] === 'unauthorized') {
                $this->ledger->clearProviderRates($property, $source->provider());
            }

            return $this->out($result['status'], 0, $result['message']);
        }

        $from = now()->startOfDay();
        $to = $from->copy()->addDays(499);
        $existing = $property->availabilities()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($row) => $row->date->format('Y-m-d'));

        $groups = [];
        foreach ($result['rates'] as $day) {
            $date = $day['date'];
            if (! $this->validDate($date) || $date < $from->toDateString() || $date > $to->toDateString()) {
                continue;
            }

            $rate = round((float) $day['rate'], 2);
            if ($rate <= 0 || ! is_finite($rate)) {
                continue;
            }

            $minimumStay = $day['min_stay'];
            $row = $existing->get($date);
            if ($row?->rates_source === 'manual') {
                continue;
            }
            if ($minimumStay === null && $row && (int) $row->min_stay_arrival > 1) {
                $minimumStay = 1;
            }

            $sameRate = $row && $row->rate !== null && round((float) $row->rate, 2) === $rate;
            $sameMinimum = $minimumStay === null || ($row && (int) $row->min_stay_arrival === $minimumStay);
            $sameOwner = $row?->rates_source === $source->provider();
            if ($sameRate && $sameMinimum && $sameOwner) {
                continue;
            }

            $groups[$rate.'|'.($minimumStay ?? '')][] = $date;
        }

        return [
            'status' => 'ready',
            'changed' => array_sum(array_map('count', $groups)),
            'message' => 'Provider rates fetched and diffed.',
            'provider' => $source->provider(),
            'refreshed_at' => $result['refreshed_at'],
            'groups' => $groups,
        ];
    }

    /**
     * Apply the diff through the ledger so Channex outbox and cache rules stay centralized.
     *
     * @param  array{status:string,changed:int,message:string,provider?:string,groups?:array,refreshed_at?:?string}  $fetched
     * @return array{status:string,changed:int,message:string}
     */
    public function apply(Property $property, array $fetched): array
    {
        if ($fetched['status'] !== 'ready') {
            return $this->out($fetched['status'], $fetched['changed'], $fetched['message']);
        }

        $provider = $fetched['provider'];
        foreach ($fetched['groups'] as $group => $dates) {
            [$rate, $minimumStay] = explode('|', (string) $group, 2);
            $values = ['rate' => (float) $rate];
            if ($minimumStay !== '') {
                $values['min_stay_arrival'] = (int) $minimumStay;
            }
            $this->ledger->setDates($property, $dates, $values, $provider);
        }

        $timestamps = [$provider.'_synced_at' => now()];
        if ($provider === 'pricelabs') {
            $timestamps['pricelabs_last_refreshed_at'] = $fetched['refreshed_at'] ?: null;
        }
        $property->forceFill($timestamps)->save();

        $changed = $fetched['changed'];

        return $this->out('ok', $changed, "{$changed} date(s) changed.");
    }

    private function validDate(string $date): bool
    {
        if (! preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $matches)) {
            return false;
        }

        return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
    }

    private function out(string $status, int $changed, string $message): array
    {
        return ['status' => $status, 'changed' => $changed, 'message' => $message];
    }
}
