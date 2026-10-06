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
    /**
     * How to read an all-numeric date whose day and month are both 12 or
     * less ("04/05/1990"). This system serves US properties, where IDs print
     * month first. Dates where one number is over 12 are unambiguous and
     * always read correctly regardless of this setting.
     */
    private const AMBIGUOUS_DATE_ORDER = 'mdy';

    private const MONTHS = [
        'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2, 'mar' => 3, 'march' => 3,
        'apr' => 4, 'april' => 4, 'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7,
        'aug' => 8, 'august' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9,
        'oct' => 10, 'october' => 10, 'nov' => 11, 'november' => 11, 'dec' => 12, 'december' => 12,
    ];

    private readonly ?string $apiKey;

    /** Set by extractRawText() when it returns null, so extract() can report why. */
    protected ?string $failureReason = null;

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
        $this->failureReason = null;

        if (blank($this->apiKey)) {
            Log::warning('IdDocumentExtractor: GOOGLE_VISION_API_KEY not configured, skipping OCR.');

            return IdExtractionResult::unavailable('no_api_key');
        }

        try {
            $rawText = $this->extractRawText($storagePath);
        } catch (\Throwable $e) {
            // The API key travels in the request URL and Guzzle appends the
            // full URL (query string included) to connection-error messages,
            // so the message must be redacted before it is logged.
            Log::error('IdDocumentExtractor: Vision API call failed.', [
                'exception' => $e::class,
                'error' => $this->redact($e->getMessage()),
            ]);

            return IdExtractionResult::unavailable('exception');
        }

        if (blank($rawText)) {
            return IdExtractionResult::unavailable($this->failureReason ?? 'empty_text');
        }

        $lines = $this->normalizeLines($rawText);

        $name = $this->findName($lines);
        $dateOfBirth = $this->findLabeledDate($lines, [
            'date of birth', 'dob', 'birth', 'naiss', 'nacimiento',
        ], preferPast: true);
        $expiryDate = $this->findLabeledDate($lines, [
            'expiry', 'expiration', 'exp date', 'exp.', 'valid until', 'valid thru', 'date of expiry',
        ], preferPast: false);

        // No personal data here on purpose: only which fields were found.
        Log::info('IdDocumentExtractor: scan complete.', [
            'lines' => count($lines),
            'name_found' => $name !== null,
            'dob_found' => $dateOfBirth !== null,
            'expiry_found' => $expiryDate !== null,
        ]);

        // Off by default. Turning this on writes the full OCR text (which is
        // personal data) to the log so the parser can be tuned against real
        // IDs. Turn it off again once done.
        if (config('services.google_vision.debug')) {
            Log::info('IdDocumentExtractor: raw OCR text (debug).', ['raw_text' => $rawText]);
        }

        return new IdExtractionResult(
            rawText: $rawText,
            name: $name,
            dateOfBirth: $dateOfBirth,
            expiryDate: $expiryDate,
        );
    }

    /**
     * Raw Vision text for an image (used for passport MRZ parsing). Null on any failure.
     */
    public function readRawText(string $storagePath): ?string
    {
        if (blank($this->apiKey)) {
            return null;
        }

        try {
            return $this->extractRawText($storagePath);
        } catch (\Throwable $e) {
            Log::error('IdDocumentExtractor: readRawText failed.', ['error' => $this->redact($e->getMessage())]);

            return null;
        }
    }

    /**
     * Calls the Vision REST API directly (a plain API key is sufficient —
     * no service account JSON / google/cloud-vision SDK dependency needed).
     */
    protected function extractRawText(string $storagePath): ?string
    {
        $contents = Storage::disk('local')->get($storagePath);

        if (blank($contents)) {
            $this->failureReason = 'file_missing';
            Log::error('IdDocumentExtractor: uploaded ID file could not be read from storage.');

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

        $json = $response->json();

        if (! $response->successful()) {
            $this->failureReason = 'http_'.$response->status();

            // 403 usually means: Vision API not enabled, billing not on, or the
            // key is restricted (e.g. HTTP-referrer restrictions block server calls).
            Log::error('IdDocumentExtractor: Vision API returned an error.', [
                'status' => $response->status(),
                'google_status' => data_get($json, 'error.status'),
                'google_reason' => data_get($json, 'error.details.0.reason'),
                'google_message' => $this->redact((string) data_get($json, 'error.message')),
                'body' => is_array($json) ? null : mb_substr($this->redact($response->body()), 0, 300),
            ]);

            return null;
        }

        // HTTP 200 can still carry a per-image error inside the payload.
        $imageError = data_get($json, 'responses.0.error');
        if ($imageError) {
            $this->failureReason = 'vision_error_'.data_get($imageError, 'code', 'unknown');

            Log::error('IdDocumentExtractor: Vision API reported an image error.', [
                'code' => data_get($imageError, 'code'),
                'message' => $this->redact((string) data_get($imageError, 'message')),
            ]);

            return null;
        }

        $text = data_get($json, 'responses.0.fullTextAnnotation.text');

        if (blank($text)) {
            $this->failureReason = 'empty_text';
        }

        return $text;
    }

    /**
     * Removes the API key from text that is about to be logged, both the
     * literal key and anything that looks like a "?key=..." query parameter.
     */
    protected function redact(string $value): string
    {
        if (filled($this->apiKey)) {
            $value = str_replace($this->apiKey, '[redacted]', $value);
        }

        return preg_replace('/([?&]key=)[^&\s)"\']+/i', '$1[redacted]', $value) ?? $value;
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
     *   1. Labeled surname + given name(s) — "Surname"/"Given Names" (passports,
     *      many national IDs) or the "LN"/"FN" labels some US states print.
     *      If both are found, combine them.
     *   2. AAMVA numbered fields ("1 DOE" surname, "2 JOHN" given names) —
     *      only accepted when BOTH numbers are present.
     *   3. A single "Name" label with the value on the same line after a
     *      colon, or on the next line, including the "LAST, FIRST" form.
     * If none of these match with reasonable confidence, return null —
     * better to route to manual review than guess. Unlabeled name lines are
     * deliberately NOT guessed at.
     */
    protected function findName(array $lines): ?string
    {
        $surname = null;
        $givenNames = null;

        foreach ($lines as $i => $line) {
            if ($surname === null && preg_match('/^(surname|last name|family name|ln)\b[:\s]*(.*)$/i', $line, $m)) {
                $surname = $this->cleanNameValue($m[2] ?? '') ?: $this->cleanNameValue($lines[$i + 1] ?? null);
            }

            if ($givenNames === null && preg_match('/^(given name|given names|first name|forename|fn)s?\b[:\s]*(.*)$/i', $line, $m)) {
                $givenNames = $this->cleanNameValue($m[2] ?? '') ?: $this->cleanNameValue($lines[$i + 1] ?? null);
            }
        }

        if ($surname && $givenNames) {
            return trim($givenNames.' '.$surname);
        }

        $numberedSurname = null;
        $numberedGiven = null;

        foreach ($lines as $i => $line) {
            if ($numberedSurname === null && preg_match('/^1\b[:\s]*(.*)$/u', $line, $m)) {
                $numberedSurname = $this->cleanNameValue($m[1] ?? '') ?: $this->cleanNameValue($lines[$i + 1] ?? null);
            }

            if ($numberedGiven === null && preg_match('/^2\b[:\s]*(.*)$/u', $line, $m)) {
                $numberedGiven = $this->cleanNameValue($m[1] ?? '') ?: $this->cleanNameValue($lines[$i + 1] ?? null);
            }
        }

        if ($numberedSurname && $numberedGiven) {
            return trim($numberedGiven.' '.$numberedSurname);
        }

        foreach ($lines as $i => $line) {
            if (preg_match('/^(full name|name)\b[:\s]*(.*)$/i', $line, $m)) {
                $value = $this->readNameValue($m[2] ?? '') ?: $this->readNameValue($lines[$i + 1] ?? null);
                if ($value) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Like cleanNameValue(), but also understands "LAST, FIRST [MIDDLE]" and
     * returns it as "FIRST [MIDDLE] LAST".
     */
    protected function readNameValue(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (preg_match('/^([^,]+),\s*([^,]+)$/u', trim($value), $m)) {
            $last = $this->cleanNameValue($m[1]);
            $first = $this->cleanNameValue($m[2]);

            return ($last && $first) ? trim($first.' '.$last) : null;
        }

        return $this->cleanNameValue($value);
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

    /**
     * Understands "15 MAR 1990", "MAR 15, 1990", "1990-03-15" and numeric
     * "03/15/1990". Numeric dates are validated as real calendar dates, never
     * "rolled over": if one number is over 12 it can only be the day, and
     * when both are 12 or less the order in AMBIGUOUS_DATE_ORDER is used.
     */
    protected function parseDate(string $text): ?Carbon
    {
        // "15 MAR 1990" (passports, some licenses)
        if (preg_match('/\b(\d{1,2})\s+([A-Za-z]{3,9})\.?\s+(\d{4})\b/', $text, $m)
            && ($month = $this->monthFromName($m[2]))) {
            return $this->buildDate((int) $m[3], $month, (int) $m[1]);
        }

        // "MAR 15, 1990"
        if (preg_match('/\b([A-Za-z]{3,9})\.?\s+(\d{1,2}),?\s+(\d{4})\b/', $text, $m)
            && ($month = $this->monthFromName($m[1]))) {
            return $this->buildDate((int) $m[3], $month, (int) $m[2]);
        }

        // "1990-03-15"
        if (preg_match('/\b(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})\b/', $text, $m)) {
            return $this->buildDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        // "03/15/1990" or "15/03/1990"
        if (preg_match('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})\b/', $text, $m)) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            $year = (int) $m[3];

            if ($first > 12) {
                return $this->buildDate($year, $second, $first);   // day / month
            }

            if ($second > 12) {
                return $this->buildDate($year, $first, $second);   // month / day
            }

            return self::AMBIGUOUS_DATE_ORDER === 'dmy'
                ? $this->buildDate($year, $second, $first)
                : $this->buildDate($year, $first, $second);
        }

        return null;
    }

    private function monthFromName(string $word): ?int
    {
        return self::MONTHS[strtolower($word)] ?? null;
    }

    /**
     * Midnight on a real calendar date, or null if the date doesn't exist.
     */
    private function buildDate(int $year, int $month, int $day): ?Carbon
    {
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day, 0, 0, 0);
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
