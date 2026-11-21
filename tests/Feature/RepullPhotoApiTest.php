<?php

namespace Tests\Feature;

use App\Services\Repull\RepullClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepullPhotoApiTest extends TestCase
{
    public function test_airbnb_photo_upload_uses_base64_payload_and_idempotency_key(): void
    {
        config([
            'repull.enabled' => true,
            'repull.api_key' => 'repull-test-key',
            'repull.base_url' => 'https://api.repull.dev',
        ]);
        Http::fake([
            'api.repull.dev/v1/channels/airbnb/listings/repull-9/photos' => Http::response(['data' => ['uploaded' => 1]]),
        ]);

        app(RepullClient::class)->uploadAirbnbPhotos('repull-9', [
            ['image' => 'aGVsbG8=', 'caption' => 'Front', 'sort_order' => 1],
        ], 'idem-photo-1');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.repull.dev/v1/channels/airbnb/listings/repull-9/photos'
            && $request['photos'][0]['image'] === 'aGVsbG8='
            && $request->hasHeader('Idempotency-Key', 'idem-photo-1'));
    }

    public function test_airbnb_photo_delete_uses_photo_id_query_parameter(): void
    {
        config([
            'repull.enabled' => true,
            'repull.api_key' => 'repull-test-key',
            'repull.base_url' => 'https://api.repull.dev',
        ]);
        Http::fake([
            'api.repull.dev/v1/channels/airbnb/listings/repull-9/photos*' => Http::response(['data' => ['deleted' => true]]),
        ]);

        app(RepullClient::class)->deleteAirbnbPhoto('repull-9', 'photo-22', 'idem-delete-22');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_contains($request->url(), 'photoId=photo-22')
            && $request->hasHeader('Idempotency-Key', 'idem-delete-22'));
    }
}
