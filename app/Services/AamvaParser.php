<?php

namespace App\Services;

/**
 * Parses the raw text of a driver-license / state-ID PDF417 barcode
 * (AAMVA standard) into plain fields. Returns null if it doesn't look like AAMVA data.
 */
class AamvaParser
{
    public static function parse(string $raw): ?array
    {
        if (! str_contains($raw, 'ANSI') && ! preg_match('/(?:DAQ|DCS|DBB)/', $raw)) {
            return null;
        }

        $get = function (string $code) use ($raw): ?string {
            // Elements are newline-separated; the first one is glued to the "DL"/"ID" subfile header.
            if (preg_match('/(?:^|[\r\n]|DL|ID)'.$code.'([^\r\n]*)/', $raw, $m)) {
                $v = trim($m[1]);

                return $v === '' ? null : $v;
            }

            return null;
        };

        $first = $get('DAC') ?? $get('DCT');
        $middle = $get('DAD');
        $last = $get('DCS');

        // Old format: DAA = "LAST,FIRST,MIDDLE"
        if (! $last && ($full = $get('DAA'))) {
            $p = array_map('trim', explode(',', $full));
            $last = $p[0] ?? null;
            $first = $first ?: ($p[1] ?? null);
            $middle = $middle ?: ($p[2] ?? null);
        }

        $country = strtoupper($get('DCG') ?? 'USA');

        return [
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'dob' => self::date($get('DBB'), $country),
            'expires_at' => self::date($get('DBA'), $country),
            'issued_at' => self::date($get('DBD'), $country),
            'id_number' => $get('DAQ'),
            'state' => $get('DAJ'),
            'country' => $country,
        ];
    }

    /** US prints MMDDYYYY, Canada prints YYYYMMDD. Try the expected order first, then the other. */
    private static function date(?string $v, string $country): ?string
    {
        if (! $v || ! preg_match('/^\d{8}$/', $v)) {
            return null;
        }

        $mdy = [substr($v, 4, 4), substr($v, 0, 2), substr($v, 2, 2)];
        $ymd = [substr($v, 0, 4), substr($v, 4, 2), substr($v, 6, 2)];

        foreach ($country === 'CAN' ? [$ymd, $mdy] : [$mdy, $ymd] as [$y, $m, $d]) {
            if ((int) $y >= 1900 && (int) $y <= 2100 && checkdate((int) $m, (int) $d, (int) $y)) {
                return sprintf('%04d-%02d-%02d', $y, $m, $d);
            }
        }

        return null;
    }
}
