<?php

namespace App\Services\Pms;

use App\Models\Property;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceLabsRateSource implements RateSource
{
    public function provider(): string
    {
        return 'pricelabs';
    }

    public function apiKeyFor(Property $property): ?string
    {
        return $property->owner?->pricelabs_api_key ?: config('services.pricelabs.api_key');
    }

    /**
     * @return array<int,array{id:string,pms:string,name:string}>
     */
    public function listings(string $apiKey): array
    {
        $response = Http::withHeaders(['X-API-Key' => $apiKey])
            ->acceptJson()
            ->timeout(30)
            ->get('https://api.pricelabs.co/v1/listings_minimal');

        if ($response->status() === 403) {
            $body = $response->json();
            $message = is_string($body) ? $body : json_encode($body);
            if (str_contains((string) $message, 'API_KEY_INVALID')) {
                throw new \RuntimeException('PriceLabs rejected this API key (403 API_KEY_INVALID). Check the key and try again.');
            }

            throw new \RuntimeException('PriceLabs rejected this API key (HTTP 403).');
        }

        if (! $response->successful()) {
            throw new \RuntimeException('PriceLabs could not verify the API key (HTTP '.$response->status().').');
        }

        $payload = $response->json();
        $items = $payload['listings'] ?? $payload['data'] ?? $payload;
        if (! is_array($items)) {
            throw new \RuntimeException('PriceLabs returned an invalid listings response.');
        }

        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => [
                'id' => (string) ($item['id'] ?? $item['listing_id'] ?? ''),
                'pms' => (string) ($item['pms'] ?? $item['pms_name'] ?? ''),
                'name' => (string) ($item['name'] ?? $item['listing_name'] ?? $item['property_name'] ?? $item['id'] ?? ''),
            ])
            ->filter(fn (array $item) => $item['id'] !== '' && $item['pms'] !== '')
            ->values()
            ->all();
    }

    public function fetch(Property $property, bool $force = false): array
    {
        $key = $this->apiKeyFor($property);
        if (! $key) {
            return $this->out('skipped', 'No PriceLabs API key is configured.', null, []);
        }
        if (! $property->pricelabs_listing_id || ! $property->pricelabs_pms) {
            return $this->out('skipped', 'No PriceLabs listing is linked.', null, []);
        }

        $from = now()->startOfDay();
        $to = $from->copy()->addDays(499);
        $response = Http::withHeaders(['X-API-Key' => $key])
            ->acceptJson()
            ->timeout(30)
            ->retry([1000, 3000, 8000], throw: false, when: function ($exception) {
                return $exception instanceof ConnectionException
                    || in_array(optional($exception->response ?? null)->status(), [429, 500, 502, 503, 504], true);
            })
            ->post('https://api.pricelabs.co/v1/listing_prices', ['listings' => [[
                'id' => (string) $property->pricelabs_listing_id,
                'pms' => (string) $property->pricelabs_pms,
                'dateFrom' => $from->toDateString(),
                'dateTo' => $to->toDateString(),
            ]]]);

        if (! $response->successful()) {
            Log::warning('PriceLabs request failed', ['property' => $property->id, 'status' => $response->status()]);

            $status = in_array($response->status(), [401, 403], true) ? 'unauthorized' : 'failed';

            return $this->out($status, 'PriceLabs returned HTTP '.$response->status().'.', null, []);
        }

        $payload = $response->json();
        $item = $payload[0] ?? $payload['listings'][0] ?? null;
        if (! is_array($item) || isset($item['error'])) {
            $message = is_array($item)
                ? (string) ($item['error_status'] ?? $item['error'] ?? 'empty response')
                : 'empty response';

            return $this->out('failed', 'PriceLabs: '.$message.'.', null, []);
        }

        $refreshed = (string) ($item['last_refreshed_at'] ?? '');
        if (! $force && $refreshed !== '' && $refreshed === $property->pricelabs_last_refreshed_at) {
            return $this->out('unchanged', 'PriceLabs has not refreshed since the last pull.', $refreshed, []);
        }

        $rates = [];
        foreach (($item['data'] ?? []) as $day) {
            if (! is_array($day) || ! isset($day['date'], $day['price']) || ! is_numeric($day['price'])) {
                continue;
            }
            $price = (float) $day['price'];
            if ($price <= 0 || ! is_finite($price)) {
                continue;
            }

            $minimumStay = isset($day['min_stay']) && is_numeric($day['min_stay']) && (int) $day['min_stay'] > 0
                ? (int) $day['min_stay']
                : null;
            $rates[] = [
                'date' => (string) $day['date'],
                'rate' => round($price, 2),
                'min_stay' => $minimumStay,
            ];
        }

        return $this->out('ok', 'PriceLabs rates fetched.', $refreshed !== '' ? $refreshed : null, $rates);
    }

    /**
     * @param  array<int,array{date:string,rate:float,min_stay:?int}>  $rates
     * @return array{status:string,message:string,refreshed_at:?string,rates:array<int,array{date:string,rate:float,min_stay:?int}>}
     */
    private function out(string $status, string $message, ?string $refreshedAt, array $rates): array
    {
        return ['status' => $status, 'message' => $message, 'refreshed_at' => $refreshedAt, 'rates' => $rates];
    }
}
