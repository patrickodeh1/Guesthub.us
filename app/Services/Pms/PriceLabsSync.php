<?php

namespace App\Services\Pms;

use App\Models\Property;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls PriceLabs' daily prices for a property and writes ONLY changed
 * dates into the availability ledger, so the Channex outbox sends deltas.
 */
class PriceLabsSync
{
    public function __construct(private AvailabilityLedger $ledger) {}

    /** @return array{status:string,changed:int,message:string} */
    public function sync(Property $p, bool $force = false): array
    {
        $key = config('services.pricelabs.api_key');

        if (! $key) {
            return $this->out('skipped', 0, 'PRICELABS_API_KEY is not set.');
        }
        if (! $p->pricelabs_listing_id || ! $p->pricelabs_pms) {
            return $this->out('skipped', 0, 'No PriceLabs listing linked.');
        }
        if (! $p->channex_rate_plan_id) {
            return $this->out('skipped', 0, 'Choose the Channex rate plan first.');
        }
        if ($p->rate_source !== 'guesthub') {
            $p->forceFill(['rate_source' => 'guesthub'])->save();
        }

        $from = now()->startOfDay();
        $to = $from->copy()->addDays(499);

        $resp = Http::withHeaders(['X-API-Key' => $key])
            ->timeout(30)
            ->retry([1000, 3000, 8000], throw: false, when: function ($e) {
                return $e instanceof \Illuminate\Http\Client\ConnectionException
                    || in_array(optional($e->response ?? null)->status(), [429, 500, 502, 503, 504], true);
            })
            ->post('https://api.pricelabs.co/v1/listing_prices', ['listings' => [[
                'id' => (string) $p->pricelabs_listing_id,
                'pms' => (string) $p->pricelabs_pms,
                'dateFrom' => $from->toDateString(),
                'dateTo' => $to->toDateString(),
            ]]]);

        if (! $resp->successful()) {
            Log::warning('PriceLabs request failed', ['property' => $p->id, 'status' => $resp->status()]);
            return $this->out('failed', 0, 'PriceLabs returned HTTP '.$resp->status().'.');
        }

        $item = $resp->json()[0] ?? null;

        if (! $item || isset($item['error'])) {
            return $this->out('failed', 0, 'PriceLabs: '.($item['error_status'] ?? $item['error'] ?? 'empty response').'.');
        }

        $refreshed = (string) ($item['last_refreshed_at'] ?? '');
        if (! $force && $refreshed !== '' && $refreshed === $p->pricelabs_last_refreshed_at) {
            return $this->out('unchanged', 0, 'PriceLabs has not refreshed since the last pull.');
        }

        $existing = $p->availabilities()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()->keyBy(fn ($r) => $r->date->format('Y-m-d'));

        $groups = [];
        foreach (($item['data'] ?? []) as $day) {
            $date = $day['date'] ?? null;
            $price = (float) ($day['price'] ?? 0);
            if (! $date || $price <= 0) {
                continue;
            }
            $rate = round($price, 2);
            $min = (int) ($day['min_stay'] ?? 0);
            $min = $min > 0 ? $min : null;

            $row = $existing->get($date);
            if ($row && $row->rates_source === 'manual') {
                continue; // set by hand in the rate editor; PriceLabs does not overwrite it
            }
            if ($min === null && $row && $row->min_stay_arrival !== null && (int) $row->min_stay_arrival > 1) {
                $min = 1; // PriceLabs no longer asks for a longer stay on this night
            }
            $sameRate = $row && $row->rate !== null && round((float) $row->rate, 2) === $rate;
            $sameMin = $min === null || ($row && (int) $row->min_stay_arrival === $min);
            if ($sameRate && $sameMin) {
                continue;
            }
            $groups[$rate.'|'.($min ?? '')][] = $date;
        }

        $changed = 0;
        foreach ($groups as $k => $dates) {
            [$rate, $min] = explode('|', $k);
            $values = ['rate' => (float) $rate];
            if ($min !== '') {
                $values['min_stay_arrival'] = (int) $min;
            }
            $this->ledger->setDates($p, $dates, $values, 'pricelabs');
            $changed += count($dates);
        }

        $p->forceFill(['pricelabs_last_refreshed_at' => $refreshed ?: null, 'pricelabs_synced_at' => now()])->save();

        return $this->out('ok', $changed, "{$changed} date(s) changed.");
    }

    private function out(string $s, int $c, string $m): array
    {
        return ['status' => $s, 'changed' => $c, 'message' => $m];
    }
}
