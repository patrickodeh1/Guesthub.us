<?php

namespace Tests\\Unit;

use App\\Services\\Pms\\PriceLabs\\PriceLabsSignature;
use Illuminate\\Http\\Request;
use PHPUnit\\Framework\\TestCase;

class PriceLabsSignatureTest extends TestCase
{
    private const TOKEN = '26eea40c-f297-4b68-aeb8-bb626fe4b5e5';

    public function test_published_pricelabs_signature_vector_matches_exactly(): void
    {
        $signature = new PriceLabsSignature();
        $signedHeaders = $signature->signHeaders(
            'request_source',
            'request_timestamp',
            'request_id',
            self::TOKEN,
        );

        $this->assertSame(
            'v1.567425e5a91b576652d591cabea78e9e1fe017d1c67e2d812ae2859c355965ff',
            $signedHeaders,
        );
        $this->assertSame(
            '5011b348ff6691bb9cbe93f85ffb836ef0e3fec45a4ce924f5940cb1aa4071b8',
            $signature->signBody($signedHeaders, '{"test":"body"}', self::TOKEN),
        );
    }

    public function test_signature_verification_rejects_tampered_body_and_headers(): void
    {
        $signature = new PriceLabsSignature();
        $body = '{"listing_id":"gh-test-1","rates":[]}';
        $signedHeaders = $signature->signHeaders('pricelabs', 'request_timestamp', 'request_id', self::TOKEN);
        $signedBody = $signature->signBody($signedHeaders, $body, self::TOKEN);

        $request = Request::create('/', 'POST', [], [], [], [], $body);
        $request->headers->set('X-SOURCE', 'pricelabs');
        $request->headers->set('X-PL-TIMESTAMP', 'request_timestamp');
        $request->headers->set('X-PL-REQUESTID', 'request_id');
        $request->headers->set('X-PL-SIGNED-HEADERS', $signedHeaders);
        $request->headers->set('X-PL-SIGNED-BODY', $signedBody);

        $this->assertTrue($signature->verify($request, self::TOKEN));

        $tamperedBody = Request::create('/', 'POST', [], [], [], [], '{"listing_id":"gh-test-2","rates":[]}');
        foreach ($request->headers->all() as $name => $values) {
            $tamperedBody->headers->set($name, $values[0]);
        }
        $this->assertFalse($signature->verify($tamperedBody, self::TOKEN));

        $tamperedHeader = clone $request;
        $tamperedHeader->headers->set('X-SOURCE', 'other-source');
        $this->assertFalse($signature->verify($tamperedHeader, self::TOKEN));
    }
}
