<?php

namespace App\\Services\\Pms\\PriceLabs;

use Illuminate\\Http\\Client\\ConnectionException;
use Illuminate\\Support\\Facades\\Http;
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
    ];

    public function enabled(): bool
    {
        return filled(config('services.pricelabs.integration_name'))
            && filled(config('services.pricelabs.integration_token'))
            && filled(config('services.pricelabs.iapi_base_url'));
    }

    /**
     * Send an IAPI request to a documented endpoint. HTTP verbs and request
     * bodies are intentionally supplied by the caller until PriceLabs makes
     * its IAPI Swagger schema available to this integration.
     */
    public function request(string $endpoint, string $method, array $payload = []): array
    {
        if (! $this->enabled()) {
            throw new RuntimeException('PriceLabs IAPI is not configured.');
        }

        $endpoint = trim($endpoint, '/');
        if (! in_array($endpoint, self::ENDPOINTS, true)) {
            throw new \\InvalidArgumentException('Unsupported PriceLabs IAPI endpoint.');
        }

        $method = strtoupper($method);
        if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new \\InvalidArgumentException('Unsupported PriceLabs IAPI HTTP method.');
        }

        $url = rtrim((string) config('services.pricelabs.iapi_base_url'), '/').'/'.$endpoint;
        $request = Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'X-INTEGRATION-NAME' => (string) config('services.pricelabs.integration_name'),
                'X-INTEGRATION-TOKEN' => (string) config('services.pricelabs.integration_token'),
            ])
            ->timeout(30)
            ->retry([1000, 3000], throw: false, when: function ($exception) {
                return $exception instanceof ConnectionException
                    || in_array(optional($exception->response ?? null)->status(), [429, 500, 502, 503, 504], true);
            });

        $response = $request->send($method, $url, $method === 'GET' ? ['query' => $payload] : ['json' => $payload]);

        if (! $response->successful()) {
            throw new RuntimeException('PriceLabs IAPI '.$endpoint.' returned HTTP '.$response->status().'.');
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    public function integration(string $method, array $payload): array
    {
        return $this->request('integration', $method, $payload);
    }

    public function listings(string $method, array $payload): array
    {
        return $this->request('listings', $method, $payload);
    }

    public function calendar(string $method, array $payload): array
    {
        return $this->request('calendar', $method, $payload);
    }

    public function reservations(string $method, array $payload): array
    {
        return $this->request('reservations', $method, $payload);
    }

    public function getPrices(string $method, array $payload): array
    {
        return $this->request('get_prices', $method, $payload);
    }

    public function status(string $method, array $payload): array
    {
        return $this->request('status', $method, $payload);
    }
}
