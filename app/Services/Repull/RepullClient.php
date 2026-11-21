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

    /**
     * Mint a short-lived storage URL, then PUT raw image bytes to storage.
     * The Repull bearer key is never forwarded to the storage host.
     */
    public function uploadListingPhoto(string $listingId, string $fileName, string $fileType, string $bytes): array
    {
        $upload = $this->request('POST', '/v1/listings/'.rawurlencode($listingId).'/photos/upload-url', [
            'fileName' => $fileName,
            'fileType' => $fileType,
            'fileSize' => strlen($bytes),
        ]);

        $uploadUrl = (string) (data_get($upload, 'uploadUrl') ?? data_get($upload, 'data.uploadUrl') ?? '');
        $publicUrl = (string) (data_get($upload, 'publicUrl') ?? data_get($upload, 'data.publicUrl') ?? '');
        $path = (string) (data_get($upload, 'path') ?? data_get($upload, 'data.path') ?? '');
        if ($uploadUrl === '' || $publicUrl === '' || $path === '') {
            throw new RepullException('Repull returned an incomplete photo upload target.', 'invalid_photo_upload_target');
        }

        $response = Http::withHeaders(['Content-Type' => $fileType])
            ->withBody($bytes, $fileType)
            ->timeout(120)
            ->put($uploadUrl);

        if (! $response->successful()) {
            throw new RepullException('Repull photo storage upload failed with HTTP '.$response->status().'.', 'photo_upload_failed', $response->status(), $response->serverError());
        }

        return ['publicUrl' => $publicUrl, 'path' => $path, 'token' => data_get($upload, 'token')];
    }

    /** Delete a canonical Repull photo by the storage path returned at upload. */
    public function deleteListingPhoto(string $listingId, string $path): array
    {
        return $this->request('DELETE', '/v1/listings/'.rawurlencode($listingId).'/photos', [], null, [
            'path' => $path,
        ]);
    }

    /** GET /v1/listings/{id}/publish-status → addressReadiness, channels[], connections[] (incl. lockedFields). */
    public function getPublishStatus(string $listingId): array
    {
        return $this->request('GET', '/v1/listings/'.rawurlencode($listingId).'/publish-status');
    }

    /** Start a Vrbo listing create or content-update job. */
    public function publishVrbo(string $listingId, array $body = [], ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/v1/listings/'.rawurlencode($listingId).'/publish/vrbo', $body, $idempotencyKey);
    }

    /** Poll a Vrbo publish job returned by publishVrbo(). */
    public function getVrboPublishJob(string $listingId, string $jobId): array
    {
        return $this->request('GET', '/v1/listings/'.rawurlencode($listingId).'/publish/vrbo', [], null, [
            'jobId' => $jobId,
        ]);
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

    /** GET/POST /v1/channels/booking/content for property licences and other granular content. */
    public function bookingContent(string $method, array $body = [], array $query = [], ?string $idempotencyKey = null): array
    {
        return $this->request($method, '/v1/channels/booking/content', $body, $idempotencyKey, $query);
    }

    /** Upload up to 20 base64-encoded Airbnb photos in one request. */
    public function uploadAirbnbPhotos(string $listingId, array $photos, ?string $idempotencyKey = null): array
    {
        return $this->request('POST', '/v1/channels/airbnb/listings/'.rawurlencode($listingId).'/photos', [
            'photos' => array_values($photos),
        ]);
    }

    /** Update an existing Airbnb photo caption and/or order. */
    public function updateAirbnbPhoto(string $listingId, array $photo, ?string $idempotencyKey = null): array
    {
        return $this->request('PATCH', '/v1/channels/airbnb/listings/'.rawurlencode($listingId).'/photos', $photo, $idempotencyKey);
    }

    /** Reorder the complete Airbnb photo tour. */
    public function orderAirbnbPhotos(string $listingId, array $photoIds, ?string $idempotencyKey = null): array
    {
        return $this->request('PUT', '/v1/channels/airbnb/listings/'.rawurlencode($listingId).'/photos/order', [
            'photo_ids' => array_values($photoIds),
        ], $idempotencyKey);
    }

    /** Remove one Airbnb photo from the channel tour. */
    public function deleteAirbnbPhoto(string $listingId, string $photoId, ?string $idempotencyKey = null): array
    {
        return $this->request('DELETE', '/v1/channels/airbnb/listings/'.rawurlencode($listingId).'/photos', [], $idempotencyKey, [
            'photoId' => $photoId,
        ]);
    }

    /** Read back Airbnb's assigned photo ids and CDN URLs. */
    public function getAirbnbPhotos(string $listingId): array
    {
        return $this->request('GET', '/v1/channels/airbnb/listings/'.rawurlencode($listingId).'/photos');
    }

    /** GET /v1/channels/airbnb/listings/{listingId}/details → locked fields and channel-specific metadata. */
    public function getAirbnbListingDetails(string $repullListingId): array
    {
        return $this->request('GET', '/v1/channels/airbnb/listings/'.rawurlencode($repullListingId).'/details');
    }

    /** POST /v1/listings/{id}/pull/airbnb — synchronously refresh the canonical copy from Airbnb. */
    public function pullFromAirbnb(string $listingId, ?string $connectionId = null): array
    {
        return $this->request('POST', '/v1/listings/'.rawurlencode($listingId).'/pull/airbnb', array_filter([
            'airbnbConnectionId' => $connectionId,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /** GET /v1/listings/{id}?include=content,details,photos,rooms,amenities,policies. */
    public function getListing(string $listingId, array $includes = ['content', 'details', 'photos', 'rooms', 'amenities', 'policies']): array
    {
        return $this->request('GET', '/v1/listings/'.rawurlencode($listingId), [], null, [
            'include' => implode(',', $includes),
        ]);
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
