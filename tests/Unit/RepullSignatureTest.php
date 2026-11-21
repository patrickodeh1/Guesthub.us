<?php

namespace Tests\Unit;

use App\Services\Repull\RepullSignature;
use PHPUnit\Framework\TestCase;

class RepullSignatureTest extends TestCase
{
    private function header(string $body, string $secret, int $t): string
    {
        return 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$body, $secret);
    }

    public function test_valid_signature_passes(): void
    {
        $t = 1_800_000_000;
        $this->assertTrue(RepullSignature::verify('{"a":1}', $this->header('{"a":1}', 's3cret', $t), 's3cret', 300, $t + 10));
    }

    public function test_tampered_body_or_wrong_secret_fails(): void
    {
        $t = 1_800_000_000;
        $h = $this->header('{"a":1}', 's3cret', $t);
        $this->assertFalse(RepullSignature::verify('{"a":2}', $h, 's3cret', 300, $t));
        $this->assertFalse(RepullSignature::verify('{"a":1}', $h, 'other', 300, $t));
    }

    public function test_stale_timestamp_is_rejected_as_replay(): void
    {
        $t = 1_800_000_000;
        $this->assertFalse(RepullSignature::verify('x', $this->header('x', 's', $t), 's', 300, $t + 301));
    }

    public function test_missing_or_malformed_header_fails(): void
    {
        foreach ([null, '', 'garbage', 't=abc,v1=zz', 'v1=deadbeef'] as $h) {
            $this->assertFalse(RepullSignature::verify('x', $h, 's', 300, 1_800_000_000));
        }
    }

    public function test_rotation_header_with_two_signatures_accepts_either(): void
    {
        $t = 1_800_000_000;
        $new = hash_hmac('sha256', $t.'.x', 'new');
        $old = hash_hmac('sha256', $t.'.x', 'old');
        $h = "t={$t},v1={$new},v1={$old}";
        $this->assertTrue(RepullSignature::verify('x', $h, 'old', 300, $t));
        $this->assertTrue(RepullSignature::verify('x', $h, 'new', 300, $t));
    }
}
