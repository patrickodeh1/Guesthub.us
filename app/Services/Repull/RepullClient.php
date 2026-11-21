<?php

namespace App\Services\Repull;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin, explicit HTTP client for the Repull native API (https://api.repull.dev).
 *
 * - Bearer auth with the single workspace key (sk_live_...).
 * - JSON is decoded with JSON_BIGINT_AS_STRING. Airbnb ids are 19 digits: PHP's 64-bit int holds
 *   them exactly (values beyond int64 become strings), but they corrupt in JS/floats. ALWAYS cast ids
 *   with (string) before storing, comparing or sending them back.
 * - Never logs request bodies or the key (listing content/PII, secrets).
 */
class RepullClient
{
    public function isConfigured(): bool
    {
        return (bool) config('repull.enabled') && filled(config('repull.api_key'));
    }

    /** @return array<string,mixed> */
    public function request(string $method, string $path, array $body = [], ?string $idempotencyKey = null, array $query = []): array
    {
        if (! $this->isConfigured()) {
            throw RepullException::notConfigured();
        }

        $url = rtrim((string) config('repull.base_url'), '/').'/'.ltrim($path, '/');
        $http = Http::withToken((string) config('repull.api_key'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('repull.timeout', 20));

        if ($idempotencyKey) {
            $http = $http->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        try {
            $response = match (strtoupper($method)) {
                'GET' => $http->get($url, $query),
                'POST' => $http->post($url, $body),
                'PUT' => $http->put($url, $body),
                'PATCH' => $http->patch($url, $body),
                'DELETE' => $http->delete($url, $body),
                default => throw new RepullException('Unsupported method '.$method, 'invalid_request'),
            };
        } catch (ConnectionException $e) {
            Log::warning('Repull unreachable', ['path' => $path]);
            throw new RepullException('Repull could not be reached.', 'connection_failed', 0, true, [], $e);
        }

        $decoded = self::decode($response->body());

        if ($response->successful()) {
            return $decoded;
        }

        $status = $response->status();
        $error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $code = (string) ($error['code'] ?? 'http_'.$status);
        $message = (string) ($error['message'] ?? 'Repull request failed.');
        // 5xx/429/408/425 are retryable; service_misconfigured is explicitly not.
        $retryable = $code !== 'service_misconfigured' && ($status >= 500 || in_array($status, [408, 425, 429], true));

        Log::warning('Repull request failed', ['path' => $path, 'status' => $status, 'code' => $code]);

        throw new RepullException($message, $code, $status, $retryable, $error);
    }

    /** @return array<string,mixed> */
    public static function decode(string $body): array
    {
        $decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);

        return is_array($decoded) ? $decoded : [];
    }

    // ── Connect ─────────────────────────────────────────────────────────────
    /** POST /v1/connect/{provider} → { sessionId, url, expiresAt }. */
    public function createChannelConnectSession(string $provider, array $payload): array
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, ['airbnb', 'booking', 'vrbo'], true)) {
            throw new RepullException('Unsupported Connect provider.', 'invalid_request');
        }

        return $this->request('POST', '/v1/connect/'.$provider, $payload);
    }

    /** POST /v1/connect → { sessionId, url, expiresAt } (multi-provider legacy flow). */
    public function createConnectSession(array $payload): array
    {
        return $this->request('POST', '/v1/connect', $payload);
    }

    // ── Listings (Repull-native, channel-agnostic) ──────────────────────────
    /** POST /v1/listings → { id } (id is a STRING). */
    public function createListing(array $payload, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/v1/listings', $payload, $idempotencyKey);
    }

    /** PUT /v1/listings/{id}/content → { id, changed[], deferred[] }. Local to Repull; reaches no channel. */
    public function updateListingContent(string $listingId, array $content): array
    {
        return $this->request('PUT', '/v1/listings/'.rawurlencode($listingId).'/content', $content);
    }

    /** GET /v1/listings/{id}/publish-status → addressReadiness, channels[], connections[] (incl. lockedFields). */
    public function getPublishStatus(string $listingId): array
    {
        return $this->request('GET', '/v1/listings/'.rawurlencode($listingId).'/publish-status');
    }

    // ── Publish ─────────────────────────────────────────────────────────────
    /** body: {hostId} to CREATE on Airbnb, or {airbnbConnectionId} to UPDATE; optional force:true. */
    public function publishAirbnb(string $listingId, array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/v1/listings/'.rawurlencode($listingId).'/publish/airbnb', $body, $idempotencyKey);
    }

    /** body: {hotelId}. The Booking.com property must exist first (bookingSetup create-property). */
    public function publishBooking(string $listingId, array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/v1/listings/'.rawurlencode($listingId).'/publish/booking', $body, $idempotencyKey);
    }

    /** GET /v1/channels/airbnb/listings → connected listings with per-listing sync state. */
    public function getAirbnbListings(array $query = []): array
    {
        return $this->request('GET', '/v1/channels/airbnb/listings', [], null, $query);
    }

    /** POST /v1/channels/booking/setup  {action: create-property|add-room|advance|..., ...} */
    public function bookingSetup(array $body, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/v1/channels/booking/setup', $body, $idempotencyKey);
    }

    /** POST /v1/channels/airbnb/listings/{id} {action: unlist|relist|push|pull|publish|delete, airbnbConnectionId} */
    public function airbnbListingAction(string $listingId, array $body): array
    {
        return $this->request('POST', '/v1/channels/airbnb/listings/'.rawurlencode($listingId), $body);
    }

    /** POST /v1/channels/booking/properties/{hotelId} {action: unlist|relist|open|close} */
    public function bookingPropertyAction(string $hotelId, array $body): array
    {
        return $this->request('POST', '/v1/channels/booking/properties/'.rawurlencode($hotelId), $body);
    }
}
