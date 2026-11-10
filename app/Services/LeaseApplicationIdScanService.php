<?php

namespace App\Services;

class LeaseApplicationIdScanService
{
    public function __construct(private readonly IdDocumentExtractor $extractor) {}

    /**
     * The OCR output is used only in memory. The application stores no DOB,
     * complete ID number, or raw OCR text.
     *
     * @return array{name: ?string, address: ?string, number_last4: ?string}
     */
    public function scan(string $path): array
    {
        $text = $this->extractor->readRawText($path);
        if (blank($text)) {
            return ['name' => null, 'address' => null, 'number_last4' => null];
        }

        $lines = collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $this->name($lines),
            'address' => $this->address($lines),
            'number_last4' => $this->numberLast4($lines),
        ];
    }

    private function name(array $lines): ?string
    {
        $surname = null;
        $given = null;

        foreach ($lines as $index => $line) {
            if ($surname === null && preg_match('/^(?:surname|last name|family name|ln)\b[:\s]*(.*)$/i', $line, $match)) {
                $surname = $this->cleanName($match[1] ?: ($lines[$index + 1] ?? ''));
            }
            if ($given === null && preg_match('/^(?:given names?|first name|forenames?|fn)\b[:\s]*(.*)$/i', $line, $match)) {
                $given = $this->cleanName($match[1] ?: ($lines[$index + 1] ?? ''));
            }
        }

        if ($surname && $given) {
            return trim($given.' '.$surname);
        }

        foreach ($lines as $index => $line) {
            if (preg_match('/^(?:full name|name)\b[:\s]+(.+)$/i', $line, $match)) {
                return $this->cleanName($match[1]);
            }
            if (preg_match('/^(?:full name|name)\s*[:#-]?\s*$/i', $line)) {
                return $this->cleanName($lines[$index + 1] ?? '');
            }
        }

        return null;
    }

    private function address(array $lines): ?string
    {
        foreach ($lines as $index => $line) {
            if (! preg_match('/^(?:street address|address|residence|addr)\b[:#\s-]*(.*)$/i', $line, $match)) {
                continue;
            }

            $address = trim($match[1] ?? '');
            if ($address === '') {
                $address = trim($lines[$index + 1] ?? '');
            }

            return $address !== '' && mb_strlen($address) <= 255 ? $address : null;
        }

        return null;
    }

    private function numberLast4(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (! preg_match('/\b(?:DLN|DL\s*(?:NO|NUMBER|#)|ID\s*(?:NO|NUMBER|#)|IDENTIFICATION\s*(?:NO|NUMBER|#)|LICENSE\s*(?:NO|NUMBER|#)|LIC\s*(?:NO|NUMBER|#)|DOCUMENT\s*(?:NO|NUMBER|#))\b\s*[:#-]?\s*([A-Z0-9][A-Z0-9 -]{2,30})/i', $line, $match)) {
                continue;
            }

            $number = preg_replace('/[^A-Z0-9]/i', '', $match[1]);
            if (strlen($number) >= 4) {
                return strtoupper(substr($number, -4));
            }
        }

        return null;
    }

    private function cleanName(string $value): ?string
    {
        $value = trim($value, " \t\n\r\0\x0B:-");
        if ($value === '' || preg_match('/^(?:given name|first name|forename|surname|last name|date|dob|sex|nationality|address)\b/i', $value)) {
            return null;
        }

        return preg_match('/\A[A-Za-zÀ-ÖØ-öø-ÿ\'\-\s.]{2,100}\z/u', $value) ? $value : null;
    }
}
