<?php

namespace App\Services\Pms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WheelhouseApiClient
{
    public function listings(string $userApiKey): array
    {
        return $this->request('GET', '/listings', $userApiKey, ['per_page' => 100]);
    }

    public function registerUser(array $user, ?string $userApiKey = null): array
    {
        return $this->request('POST', '/user_registration', $userApiKey, [], $user);
    }

    public function upsertListing(string $listingId, array $listing, string $userApiKey): array
    {
        return $this->request('PUT', '/listings/'.rawurlencode($listingId), $userApiKey, [], $listing);
    }

    public function recommendations(string $listingId, string $userApiKey, ?string $channel = null): array
    {
        return $this->request(
            'GET',
            '/listings/'.rawurlencode($listingId).'/price_recommendations',
            $userApiKey,
            array_filter(['channel' => $channel, 'currency' => 'USD']),
        );
    }

    private function request(string $method, string $path, ?string $userApiKey = null, array $query = [], ?array $payload = null): array
    {
        $integrationKey = config('services.wheelhouse.partner_api_key');
        if (! $integrationKey) {
            throw new WheelhouseApiException('Wheelhouse partner API key is not configured.');
        }

        $headers = ['X-Integration-Api-Key' => $integrationKey];
        if ($userApiKey) {
            $headers['X-User-API-Key'] = $userApiKey;
        }

        $request = Http::acceptJson()
            ->withHeaders($headers)
            ->timeout(30)
            ->retry([1000, 3000], throw: false, when: function ($exception) {
                return $exception instanceof ConnectionException
                    || in_array(optional($exception->response ?? null)->status(), [429, 500, 502, 503, 504], true);
            });
        $url = rtrim((string) config('services.wheelhouse.base_url'), '/').'/'.ltrim($path, '/');

        $response = match (strtoupper($method)) {
            'GET' => $request->get($url, $query),
            'POST' => $request->post($url, $payload ?? []),
            'PUT' => $request->put($url, $payload ?? []),
            default => throw new \InvalidArgumentException('Unsupported Wheelhouse HTTP method.'),
        };

        if (! $response->successful()) {
            throw new WheelhouseApiException('Wheelhouse returned HTTP '.$response->status().'.', $response->status());
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }
}
