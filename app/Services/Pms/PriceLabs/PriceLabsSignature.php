<?php

namespace App\\Services\\Pms\\PriceLabs;

use Illuminate\\Http\\Request;

class PriceLabsSignature
{
    public function signHeaders(string $source, string $timestamp, string $requestId, string $token): string
    {
        $components = 'v1:'.$source.':'.$timestamp.':'.$requestId;

        return 'v1.'.hash_hmac('sha256', $components, $token);
    }

    public function signBody(string $signedHeaders, string $rawBody, string $token): string
    {
        // PriceLabs' published test vector signs the JSON string with its quotes
        // escaped once after the signed header. Keep the raw request body intact
        // for JSON decoding; escape only for this signature calculation.
        return hash_hmac('sha256', $signedHeaders.addslashes($rawBody), $token);
    }

    public function verify(Request $request, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $source = (string) $request->header('X-SOURCE', '');
        $timestamp = (string) $request->header('X-PL-TIMESTAMP', '');
        $requestId = (string) $request->header('X-PL-REQUESTID', '');
        $providedHeaders = (string) $request->header('X-PL-SIGNED-HEADERS', '');
        $providedBody = (string) $request->header('X-PL-SIGNED-BODY', '');

        if ($source === '' || $timestamp === '' || $requestId === '' || $providedHeaders === '' || $providedBody === '') {
            return false;
        }

        $version = explode('.', $providedHeaders, 2)[0] ?? '';
        if ($version !== 'v1') {
            return false;
        }

        $expectedHeaders = $this->signHeaders($source, $timestamp, $requestId, $token);
        if (! hash_equals($expectedHeaders, $providedHeaders)) {
            return false;
        }

        $expectedBody = $this->signBody($expectedHeaders, $request->getContent(), $token);

        return hash_equals($expectedBody, $providedBody);
    }
}
