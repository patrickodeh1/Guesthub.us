<?php

namespace App\Services\Channels;

/**
 * Airbnb rejects descriptions/house rules that contain links, emails or phone
 * numbers ("Links and contact info can't be shared"). Strip them before a push
 * and tell the host what was removed. Pure PHP (unit-testable without Laravel).
 */
final class ContentSanitizer
{
    /** @return array{0:string,1:list<string>} [cleanText, removedKinds] */
    public static function forOta(?string $text): array
    {
        $text = (string) $text;
        $removed = [];

        $patterns = [
            'link' => '~(?:https?://|www\.)\S+~i',
            'email' => '~[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}~i',
            // 10+ digits with optional separators (phone-like). Avoids short numbers/years.
            'phone' => '~(?<!\d)(?:\+?\d[\s().-]{0,2}){9,}\d(?!\d)~',
        ];

        foreach ($patterns as $kind => $regex) {
            $count = 0;
            $text = (string) preg_replace($regex, '', $text, -1, $count);
            if ($count > 0) {
                $removed[] = $kind;
            }
        }

        // Tidy whitespace left behind.
        $text = (string) preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = (string) preg_replace('/ ?\n ?/', "\n", $text);

        return [trim($text), $removed];
    }
}
