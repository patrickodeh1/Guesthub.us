<?php

namespace App\Support;

class PhoneFormatter
{
    public static function normalizeForStorage(?string $raw, string $countryCode = '+1'): ?string
    {
        if (! $raw) {
            return null;
        }

        $countryDigits = preg_replace('/\D+/', '', $countryCode);
        $digits = preg_replace('/\D+/', '', $raw);

        if (! $digits) {
            return null;
        }

        if ($countryDigits && str_starts_with($digits, $countryDigits) && strlen($digits) > strlen($countryDigits) + 10) {
            $digits = substr($digits, strlen($countryDigits));
        }

        if ($countryDigits === '1' && strlen($digits) === 11 && str_starts_with($digits, '1')) {
            $digits = substr($digits, 1);
        }

        return '+'.$countryDigits.$digits;
    }

    public static function toTelUri(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (strlen($digits) === 10) {
            $digits = '1'.$digits;
        }

        return $digits ? '+'.$digits : null;
    }

    /**
     * Format a stored phone number for display: "(000) 123-4567".
     * Handles values like "+1 5551234567", "5551234567", "+15551234567".
     * Falls back to returning the original string if it doesn't look
     * like a 10-digit US/CA number (e.g. international numbers).
     */
    public static function format(?string $raw): ?string
    {
        if (! $raw) {
            return $raw;
        }

        $digits = preg_replace('/\D+/', '', $raw);

        // Strip a leading country code of "1" if present (US/CA), leaving 10 digits.
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            // Not a standard 10-digit US/CA number — return as-is rather than mangle it.
            return $raw;
        }

        return sprintf(
            '(%s) %s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 4)
        );
    }
}
