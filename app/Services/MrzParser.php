<?php

namespace App\Services;

/**
 * Finds and validates a passport (TD3) MRZ inside OCR text.
 * Returns fields only if the check digits pass, so a bad OCR read can never be accepted.
 */
class MrzParser
{
    public static function parseFromText(string $text): ?array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', strtoupper($text)) as $l) {
            $l = preg_replace('/\s+/', '', $l);
            if (strlen($l) >= 40 && substr_count($l, '<') >= 3) {
                $lines[] = $l;
            }
        }

        for ($i = 0; $i < count($lines) - 1; $i++) {
            $l1 = str_pad(substr($lines[$i], 0, 44), 44, '<');
            $l2 = str_pad(substr($lines[$i + 1], 0, 44), 44, '<');
            if ($l1[0] === 'P' && ($r = self::parseTd3($l1, $l2))) {
                return $r;
            }
        }

        return null;
    }

    public static function parseTd3(string $l1, string $l2): ?array
    {
        // Positions that must be digits (number check, dob+check, expiry+check, final checks)
        $digitPos = array_merge([9], range(13, 19), range(21, 27), [42, 43]);
        $chars = str_split($l2);
        foreach ($digitPos as $p) {
            $chars[$p] = strtr($chars[$p], ['O' => '0', 'Q' => '0', 'I' => '1', 'L' => '1', 'S' => '5', 'Z' => '2', 'B' => '8']);
        }
        // Nationality must be letters
        foreach ([10, 11, 12] as $p) {
            $chars[$p] = strtr($chars[$p], ['0' => 'O', '1' => 'I', '5' => 'S', '8' => 'B', '2' => 'Z']);
        }
        $l2 = implode('', $chars);

        $ok = self::check(substr($l2, 0, 9)) === (int) $l2[9]
            && self::check(substr($l2, 13, 6)) === (int) $l2[19]
            && self::check(substr($l2, 21, 6)) === (int) $l2[27]
            && self::check(substr($l2, 0, 10).substr($l2, 13, 7).substr($l2, 21, 22)) === (int) $l2[43];

        if (! $ok) {
            return null;
        }

        $names = explode('<<', rtrim(substr($l1, 5), '<'), 2);
        $clean = fn ($s) => trim(preg_replace('/\s+/', ' ', str_replace('<', ' ', strtr($s, ['0' => 'O']))));

        return [
            'last_name' => $clean($names[0] ?? ''),
            'first_name' => $clean($names[1] ?? ''),
            'id_number' => rtrim(substr($l2, 0, 9), '<'),
            'country' => substr($l2, 10, 3),
            'dob' => self::date(substr($l2, 13, 6), true),
            'expires_at' => self::date(substr($l2, 21, 6), false),
        ];
    }

    private static function check(string $s): int
    {
        $w = [7, 3, 1];
        $sum = 0;
        foreach (str_split($s) as $i => $c) {
            $v = ctype_digit($c) ? (int) $c : (ctype_alpha($c) ? ord($c) - 55 : 0);
            $sum += $v * $w[$i % 3];
        }

        return $sum % 10;
    }

    private static function date(string $yymmdd, bool $isDob): ?string
    {
        if (! preg_match('/^\d{6}$/', $yymmdd)) {
            return null;
        }
        $yy = (int) substr($yymmdd, 0, 2);
        $m = (int) substr($yymmdd, 2, 2);
        $d = (int) substr($yymmdd, 4, 2);
        $year = $isDob ? ($yy > (int) date('y') ? 1900 + $yy : 2000 + $yy) : 2000 + $yy;

        return checkdate($m, $d, $year) ? sprintf('%04d-%02d-%02d', $year, $m, $d) : null;
    }
}
