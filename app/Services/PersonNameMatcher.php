<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Compares a name read off a government ID with the name a guest typed.
 *
 * IDs and booking forms rarely agree character-for-character: the ID has a
 * middle name the guest left out, "JOSÉ" vs "Jose", "O'BRIEN" with a
 * typographic apostrophe, "SMITH-JONES" vs "Smith Jones", a "JR" suffix, or
 * the words in a different order. Those must not be treated as a mismatch,
 * because a mismatch blocks the guest from signing the rental agreement.
 *
 * What still counts as a mismatch: a genuinely different first or last name
 * (e.g. typed "John Smith", ID says "JOHN DOE"), or a single-word name.
 */
class PersonNameMatcher
{
    private const SUFFIXES = ['jr', 'sr', 'ii', 'iii', 'iv'];

    /**
     * Lowercase, accents folded to ASCII. Apostrophes and periods are removed
     * ("O'Brien" -> "obrien", "Jr." -> "jr"); any other run of
     * non-alphanumeric characters (hyphens, commas, ...) becomes one space.
     */
    public static function normalize(?string $name): string
    {
        $name = mb_strtolower(trim((string) $name));
        $name = preg_replace('/[\'\x{2019}\x{2018}`\x{00B4}.]/u', '', $name) ?? '';
        $name = Str::ascii($name);
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? '';

        return trim($name);
    }

    /**
     * @return string[]
     */
    public static function tokens(?string $name): array
    {
        $normalized = self::normalize($name);

        if ($normalized === '') {
            return [];
        }

        $tokens = explode(' ', $normalized);

        // Drop a trailing generational suffix ("John Doe Jr" -> "john doe"),
        // but never reduce a name to fewer than two words.
        if (count($tokens) >= 3 && in_array(end($tokens), self::SUFFIXES, true)) {
            array_pop($tokens);
        }

        return $tokens;
    }

    public static function matches(?string $extracted, ?string $typed): bool
    {
        $a = self::tokens($extracted);
        $b = self::tokens($typed);

        if ($a === [] || $b === []) {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        // Anything looser than an exact match needs at least two words on
        // both sides, so a lone "John" never matches "John Doe".
        if (count($a) < 2 || count($b) < 2) {
            return false;
        }

        // Same first and last word (middle names ignored).
        if ($a[0] === $b[0] && end($a) === end($b)) {
            return true;
        }

        // Same words in a different order ("DOE JOHN" vs "John Doe").
        $sortedA = $a;
        $sortedB = $b;
        sort($sortedA);
        sort($sortedB);
        if ($sortedA === $sortedB) {
            return true;
        }

        // One side is entirely contained in the other (middle name present
        // on only one of them, compound surnames, etc.).
        return array_diff($b, $a) === [] || array_diff($a, $b) === [];
    }


    /**
     * Compares separately-known first/last names (from the barcode or MRZ)
     * with the single typed name. Middle names on either side are ignored.
     *
     * @return string 'match' | 'close' | 'different' | 'unknown' (a part was missing, never a mismatch)
     */
    public static function compareParts(?string $first, ?string $last, ?string $typed): string
    {
        $strip = fn (array $t) => array_values(array_filter(
            $t,
            fn ($x) => $x !== '' && ! in_array($x, self::SUFFIXES, true)
        ));

        $firstT = $strip(explode(' ', self::normalize($first)));
        $lastT = $strip(explode(' ', self::normalize($last)));
        $typedT = $strip(explode(' ', self::normalize($typed)));

        if ($firstT === [] || $lastT === [] || $typedT === []) {
            return 'unknown';
        }

        $lastJoined = implode('', $lastT);
        $typedJoined = implode('', $typedT);

        $lastOk = array_diff($lastT, $typedT) === []
            || in_array($lastJoined, $typedT, true)
            || (strlen($lastJoined) >= 5 && str_ends_with($typedJoined, $lastJoined));
        $firstOk = array_intersect($firstT, $typedT) !== [];

        if ($lastOk && $firstOk) {
            return 'match';
        }

        if ($lastOk) {
            foreach ($typedT as $t) {
                foreach ($firstT as $f) {
                    if ((strlen($t) === 1 && $t === $f[0])
                        || (strlen($f) === 1 && $f === $t[0])
                        || (min(strlen($t), strlen($f)) >= 3 && levenshtein($t, $f) <= 1)) {
                        return 'close';
                    }
                }
            }
        }

        if ($firstOk && strlen($lastJoined) >= 5) {
            foreach ($typedT as $t) {
                if (levenshtein($t, $lastJoined) <= 1) {
                    return 'close';
                }
            }
        }

        return 'different';
    }
}
