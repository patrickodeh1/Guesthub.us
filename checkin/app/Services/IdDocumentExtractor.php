<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Reads the name, date of birth, and expiry date off a photo of a
 * government ID (passport or driver's license/state ID) using Google
 * Cloud Vision's DOCUMENT_TEXT_DETECTION.
 *
 * We deliberately do NOT use MRZ parsing or a dedicated ID-parsing API
 * (Textract AnalyzeID, Document AI ID model, etc.) — the client only
 * needs the plain inscribed fields, and plain OCR + our own field
 * matching is free at this volume (Vision gives 1,000 free units/month
 * for this feature). If Vision's accuracy proves insufficient in
 * production, swap the vendor call in extractRawText() below — nothing
 * else in this class or its caller needs to change.
 *
 * A field we can't confidently locate is left null rather than guessed,
 * so the caller can route to manual review instead of a false
 * accept/reject. Never trust a single low-confidence read into an
 * automatic instant-reject — only a *clearly* expired date or a
 * *clearly* mismatched name should auto-reject.
 */
class IdDocumentExtractor
{
    private readonly ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.google_vision.key');
    }

    /**
     * @param  string  $storagePath  Path on the `local` disk (as stored by
     *                                the guest upload, e.g. photo_id_path).
     */
    public function extract(string $storagePath): IdExtractionResult
    {
        if (blank($this->apiKey)) {
            Log::warning('IdDocumentExtractor: GOOGLE_VISION_API_KEY not configured, skipping OCR.');

            return IdExtractionResult::unavailable();
        }

        try {
            $rawText = $this->extractRawText($storagePath);
        } catch (\Throwable $e) {
            Log::error('IdDocumentExtractor: Vision API call failed.', ['error' => $e->getMessage()]);

            return IdExtractionResult::unavailable();
        }

        if (blank($rawText)) {
            return IdExtractionResult::unavailable();
        }

        $lines = $this->normalizeLines($rawText);

        return new IdExtractionResult(
            rawText: $rawText,
            name: $this->findName($lines),
            dateOfBirth: $this->findLabeledDate($lines, [
                'date of birth', 'dob', 'birth', 'naiss', 'nacimiento',
            ], preferPast: true),
            expiryDate: $this->findLabeledDate($lines, [
                'expiry', 'expiration', 'exp date', 'exp.', 'valid until', 'valid thru', 'date of expiry',
            ], preferPast: false),
        );
    }

    /**
     * Calls the Vision REST API directly (a plain API key is sufficient —
     * no service account JSON / google/cloud-vision SDK dependency needed).
     */
    protected function extractRawText(string $storagePath): ?string
    {
        $contents = Storage::disk('local')->get($storagePath);

        if (blank($contents)) {
            return null;
        }

        $response = Http::timeout(20)->post(
            'https://vision.googleapis.com/v1/images:annotate?key='.$this->apiKey,
            [
                'requests' => [[
                    'image' => ['content' => base64_encode($contents)],
                    'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                ]],
            ]
        );

        if (! $response->successful()) {
            Log::error('IdDocumentExtractor: Vision API returned an error.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        return data_get($response->json(), 'responses.0.fullTextAnnotation.text');
    }

    /**
     * @return string[]
     */
    protected function normalizeLines(string $rawText): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $rawText))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();
    }

    /**
     * Names on IDs are the hardest field: no universal label. Strategy,
     * in order of preference:
     *   1. A line explicitly labeled "Name" / "Surname" + "Given Name(s)"
     *      (common on driver's licenses and many national ID cards) — if
     *      both are found, combine them.
     *   2. A single "Name" label with the value on the same line after a
     *      colon, or on the next line.
     * If neither pattern is found with reasonable confidence, return null
     * — better to route to manual review than guess.
     */
    protected function findName(array $lines): ?string
    {
        $surname = null;
        $givenNames = null;

        foreach ($lines as $i => $line) {
            $lower = mb_strtolower($line);

            if ($surname === null && preg_match('/^(surname|last name|family name)\b[:\s]*(.*)$/i', $line, $m)) {
                $surname = $this->cleanNameValue($m[2] ?? '') ?: $this->cleanNameValue($lines[$i + 1] ?? null);
            }

            if ($givenNames === null && preg_match('/^(given name|given names|first name|forename)s?\b[:\s]*(.*)$/i', $line, $m)) {
                $givenNames = $this->cleanNameValue($m[2] ?? '') ?: $this->cleanNameValue($lines[$i + 1] ?? null);
            }
        }

        if ($surname && $givenNames) {
            return trim($givenNames.' '.$surname);
        }

        foreach ($lines as $i => $line) {
            if (preg_match('/^(full name|name)\b[:\s]*(.*)$/i', $line, $m)) {
                $value = $this->cleanNameValue($m[2] ?? '') ?: $this->cleanNameValue($lines[$i + 1] ?? null);
                if ($value) {
                    return $value;
                }
            }
        }

        return null;
    }

    protected function cleanNameValue(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = trim($value, " \t\n\r\0\x0B:-");

        // A name value should look like a name, not another label we
        // walked into (e.g. the line right after "Surname" was actually
        // "Given Name: John" because nothing was on the surname line).
        if ($value === '' || preg_match('/^(given name|first name|forename|surname|last name|date|dob|sex|nationality|address)/i', $value)) {
            return null;
        }

        return preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\'\-\s.]{2,60}$/u', $value) ? $value : null;
    }

    /**
     * Finds a date near one of the given label keywords. Checks the same
     * line (after the label) first, then the following line. `preferPast`
     * is used only as a plausibility filter (DOB must be in the past and
     * under 120 years ago; expiry has no such requirement) — it does not
     * change which label we search for.
     */
    protected function findLabeledDate(array $lines, array $labelKeywords, bool $preferPast): ?Carbon
    {
        foreach ($lines as $i => $line) {
            $lower = mb_strtolower($line);

            foreach ($labelKeywords as $keyword) {
                if (! str_contains($lower, $keyword)) {
                    continue;
                }

                $date = $this->parseDate($line) ?? $this->parseDate($lines[$i + 1] ?? '');

                if ($date && $this->isPlausibleDate($date, $preferPast)) {
                    return $date;
                }
            }
        }

        return null;
    }

    protected function parseDate(string $text): ?Carbon
    {
        // Matches DD-MM-YYYY, DD/MM/YYYY, YYYY-MM-DD, and "DD MON YYYY"
        // (e.g. "14 MAR 2030", common on passports/licenses), in either
        // order. We try several formats since ID layouts vary widely by
        // issuing country/state.
        $patterns = [
            '/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})\b/' => 'd-m-Y',
            '/\b(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})\b/' => 'Y-m-d',
            '/\b(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})\b/' => null, // handled separately below
        ];

        if (preg_match('/\b(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})\b/', $text, $m)) {
            try {
                return Carbon::createFromFormat('d M Y', $m[1].' '.mb_substr($m[2], 0, 3).' '.$m[3]);
            } catch (\Throwable) {
                // fall through to numeric patterns
            }
        }

        if (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})\b/', $text, $m)) {
            try {
                return Carbon::createFromFormat('d-m-Y', $m[1].'-'.$m[2].'-'.$m[3]);
            } catch (\Throwable) {
                // ambiguous DD/MM vs MM/DD — try the other order before giving up
                try {
                    return Carbon::createFromFormat('m-d-Y', $m[1].'-'.$m[2].'-'.$m[3]);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        if (preg_match('/\b(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})\b/', $text, $m)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $m[1].'-'.$m[2].'-'.$m[3]);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    protected function isPlausibleDate(Carbon $date, bool $preferPast): bool
    {
        if ($preferPast) {
            return $date->lessThan(now()) && $date->greaterThan(now()->subYears(120));
        }

        // Expiry: allow a wide window either side rather than rejecting —
        // an already-expired date is exactly what we need to detect, not
        // filter out.
        return $date->greaterThan(now()->subYears(30)) && $date->lessThan(now()->addYears(30));
    }
}
