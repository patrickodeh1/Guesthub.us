<?php

namespace App\Services\Pms;

use App\Models\Property;

class WheelhouseRateSource implements RateSource
{
    public function __construct(private WheelhouseApiClient $client) {}

    public function provider(): string
    {
        return 'wheelhouse';
    }

    public function fetch(Property $property, bool $force = false): array
    {
        $userApiKey = $property->owner?->wheelhouse_user_api_key;
        if (! $userApiKey || ! $property->wheelhouse_listing_id) {
            return $this->out('skipped', 'Connect a Wheelhouse account and listing first.', []);
        }

        try {
            $payload = $this->client->recommendations(
                (string) $property->wheelhouse_listing_id,
                $userApiKey,
                $property->wheelhouse_channel,
            );
        } catch (WheelhouseApiException $exception) {
            $status = in_array($exception->status, [401, 403], true) ? 'unauthorized' : 'failed';

            return $this->out($status, $exception->getMessage(), []);
        }

        $days = $payload['data'] ?? $payload;
        if (! is_array($days)) {
            return $this->out('failed', 'Wheelhouse returned an invalid recommendations response.', []);
        }

        $rates = [];
        foreach ($days as $day) {
            if (! is_array($day)
                || ! isset($day['stay_date'], $day['price'])
                || ! is_numeric($day['price'])) {
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
                'date' => (string) $day['stay_date'],
                'rate' => round($price, 2),
                'min_stay' => $minimumStay,
            ];
        }

        return $this->out('ok', 'Wheelhouse rates fetched.', $rates);
    }

    /**
     * @param  array<int,array{date:string,rate:float,min_stay:?int}>  $rates
     * @return array{status:string,message:string,refreshed_at:?string,rates:array<int,array{date:string,rate:float,min_stay:?int}>}
     */
    private function out(string $status, string $message, array $rates): array
    {
        return ['status' => $status, 'message' => $message, 'refreshed_at' => null, 'rates' => $rates];
    }
}
