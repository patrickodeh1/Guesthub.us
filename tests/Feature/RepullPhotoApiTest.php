<?php

namespace Tests\Feature;

use App\Services\Repull\RepullClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepullPhotoApiTest extends TestCase
{
    public function test_canonical_photo_upload_mints_target_then_puts_bytes_without_forwarding_api_key(): void
    {
        config([
            'repull.enabled' => true,
            'repull.api_key' => 'repull-test-key',
            'repull.base_url' => 'https://api.repull.dev',
        ]);
        Http::fake([
            'api.repull.dev/v1/listings/repull-9/photos/upload-url' => Http::response([
                'uploadUrl' => 'https://storage.example.com/signed-upload',
                'publicUrl' => 'https://cdn.repull.dev/listings/repull-9/cover.jpg',
                'path' => 'listings/repull-9/cover.jpg',
            ]),
            'storage.example.com/signed-upload' => Http::response('', 200),
        ]);

        $result = app(RepullClient::class)->uploadListingPhoto('repull-9', 'cover.jpg', 'image/jpeg', 'image-bytes');

        $this->assertSame('https://cdn.repull.dev/listings/repull-9/cover.jpg', $result['publicUrl']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.repull.dev/v1/listings/repull-9/photos/upload-url'
            && $request['fileName'] === 'cover.jpg'
            && $request['fileType'] === 'image/jpeg'
            && $request['fileSize'] === 11
            && $request->hasHeader('Authorization', 'Bearer repull-test-key')
            && ! $request->hasHeader('Idempotency-Key'));
        Http::assertSent(fn ($request) => $request->url() === 'https://storage.example.com/signed-upload'
            && $request->method() === 'PUT'
            && $request->body() === 'image-bytes'
            && ! $request->hasHeader('Authorization'));
    }

    public function test_canonical_photo_delete_uses_the_returned_storage_path(): void
    {
        config([
            'repull.enabled' => true,
            'repull.api_key' => 'repull-test-key',
            'repull.base_url' => 'https://api.repull.dev',
        ]);
        Http::fake([
            'api.repull.dev/v1/listings/repull-9/photos*' => Http::response(['deleted' => true]),
        ]);

        app(RepullClient::class)->deleteListingPhoto('repull-9', 'listings/repull-9/cover.jpg');

        Http::assertSent(function ($request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            return $request->method() === 'DELETE'
                && ($query['path'] ?? null) === 'listings/repull-9/cover.jpg'
                && ! $request->hasHeader('Idempotency-Key');
        });
    }
}
