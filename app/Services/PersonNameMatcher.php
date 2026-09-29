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
}
