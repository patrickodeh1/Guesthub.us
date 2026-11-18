<?php

namespace App\Services\Pms\PriceLabs;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PriceLabsIapiClient
{
    private const ENDPOINTS = [
        'integration',
        'listings',
        'calendar',
        'reservations',
        'get_prices',
        'status',
        'sync_status',
        'rate_plans',
    ];

    private const TIMEOUT_SECONDS = 300;
    private const MAX_ATTEMPTS = 4;

    public function enabled(): bool
    {
        return filled(config('services.pricelabs.integration_name'))
            && filled(config('services.pricelabs.integration_token'))
            && filled(config('services.pricelabs.iapi_base_url'));
    }

    /**
     * Send a request to a documented PriceLabs v2 IAPI endpoint.
     *
     * Rate limits are scoped to the partner token (300 requests/minute).
     * A 429 response is retried using PriceLabs' Retry-After header.
     */
    public function request(string $endpoint, string $method, array $payload = []): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('PriceLabs IAPI is not configured.');
        }

        $endpoint = trim($endpoint, '/');
        if (! in_array($endpoint, self::ENDPOINTS, true)) {
            throw new \InvalidArgumentException('Unsupported PriceLabs IAPI endpoint.');
        }

        $method = strtoupper($method);
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new \InvalidArgumentException('Unsupported PriceLabs IAPI HTTP method.');
        }

        $url = rtrim((string) config('services.pricelabs.iapi_base_url'), '/').'/'.$endpoint;
        $request = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'X-INTEGRATION-NAME' => (string) config('services.pricelabs.integration_name'),
                'X-INTEGRATION-TOKEN' => (string) config('services.pricelabs.integration_token'),
            ])
            ->timeout(self::TIMEOUT_SECONDS);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = $request->send(
                    $method,
                    $url,
                    $method === 'GET' ? ['query' => $payload] : ['json' => $payload],
                );
            } catch (ConnectionException $exception) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw new RuntimeException('PriceLabs IAPI '.$endpoint.' connection failed after '.self::MAX_ATTEMPTS.' attempts.', 0, $exception);
                }

                usleep($attempt * 500_000);
                continue;
            }

            if ($response->successful()) {
                $body = $response->json();

                return is_array($body) ? $body : [];
            }

            if ($response->status() === 429 && $attempt < self::MAX_ATTEMPTS) {
                $retryAfter = $response->header('Retry-After');
                $delay = is_numeric($retryAfter) ? max(1, (int) $retryAfter) : $attempt;
                sleep($delay);
                continue;
            }

            if (in_array($response->status(), [500, 502, 503, 504], true) && $attempt < self::MAX_ATTEMPTS) {
                usleep($attempt * 500_000);
                continue;
            }

            throw new RuntimeException('PriceLabs IAPI '.$endpoint.' returned HTTP '.$response->status().'.');
        }

        throw new RuntimeException('PriceLabs IAPI '.$endpoint.' request failed.');
    }

    public function integration(string $method, array $payload = []): array
    {
        return $this->request('integration', $method, $payload);
    }

    public function listings(string $method, array $payload = []): array
    {
        return $this->request('listings', $method, $payload);
    }

    public function calendar(string $method, array $payload = []): array
    {
        return $this->request('calendar', $method, $payload);
    }

    public function reservations(string $method, array $payload = []): array
    {
        return $this->request('reservations', $method, $payload);
    }

    public function getPrices(string $method, array $payload = []): array
    {
        return $this->request('get_prices', $method, $payload);
    }

    public function status(string $method, array $payload = []): array
    {
        return $this->request('status', $method, $payload);
    }

    public function syncStatus(string $method = 'GET', array $payload = []): array
    {
        return $this->request('sync_status', $method, $payload);
    }

    public function ratePlans(string $method, array $payload = []): array
    {
        return $this->request('rate_plans', $method, $payload);
    }
}
