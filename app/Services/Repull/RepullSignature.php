<?php

namespace App\Services\Repull;

/**
 * Verifies Repull webhook signatures.
 *
 *   X-Repull-Signature: t=<unix>,v1=<hex hmac-sha256 of "<t>.<raw body>">
 *
 * Pure PHP (no framework) so it can be unit-tested in isolation. During a secret
 * rotation the header may carry several v1= values; any match is accepted.
 */
final class RepullSignature
{
    public static function verify(string $rawBody, ?string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
    {
        if ($header === null || $header === '' || $secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            if ($pair[0] === 't') {
                $timestamp = ctype_digit($pair[1]) ? (int) $pair[1] : null;
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(($now ?? time()) - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
