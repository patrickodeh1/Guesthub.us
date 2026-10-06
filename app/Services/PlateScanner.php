<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads a license plate (text + state) from a photo using the Google Vision key
 * already configured for ID scanning. The image is never stored here.
 * Characters are NOT auto-corrected (O/0, I/1); the guest confirms the result.
 */
class PlateScanner
{
    private const STATES = [
        'ALABAMA' => 'AL', 'ALASKA' => 'AK', 'ARIZONA' => 'AZ', 'ARKANSAS' => 'AR', 'CALIFORNIA' => 'CA',
        'COLORADO' => 'CO', 'CONNECTICUT' => 'CT', 'DELAWARE' => 'DE', 'DISTRICT OF COLUMBIA' => 'DC',
        'FLORIDA' => 'FL', 'GEORGIA' => 'GA', 'HAWAII' => 'HI', 'IDAHO' => 'ID', 'ILLINOIS' => 'IL',
        'INDIANA' => 'IN', 'IOWA' => 'IA', 'KANSAS' => 'KS', 'KENTUCKY' => 'KY', 'LOUISIANA' => 'LA',
        'MAINE' => 'ME', 'MARYLAND' => 'MD', 'MASSACHUSETTS' => 'MA', 'MICHIGAN' => 'MI', 'MINNESOTA' => 'MN',
        'MISSISSIPPI' => 'MS', 'MISSOURI' => 'MO', 'MONTANA' => 'MT', 'NEBRASKA' => 'NE', 'NEVADA' => 'NV',
        'NEW HAMPSHIRE' => 'NH', 'NEW JERSEY' => 'NJ', 'NEW MEXICO' => 'NM', 'NEW YORK' => 'NY',
        'NORTH CAROLINA' => 'NC', 'NORTH DAKOTA' => 'ND', 'OHIO' => 'OH', 'OKLAHOMA' => 'OK', 'OREGON' => 'OR',
        'PENNSYLVANIA' => 'PA', 'RHODE ISLAND' => 'RI', 'SOUTH CAROLINA' => 'SC', 'SOUTH DAKOTA' => 'SD',
        'TENNESSEE' => 'TN', 'TEXAS' => 'TX', 'UTAH' => 'UT', 'VERMONT' => 'VT', 'VIRGINIA' => 'VA',
        'WASHINGTON' => 'WA', 'WEST VIRGINIA' => 'WV', 'WISCONSIN' => 'WI', 'WYOMING' => 'WY',
    ];

    public function scan(string $imageBytes): array
    {
        $key = config('services.google_vision.key');
        if (blank($key)) {
            return ['ok' => false, 'reason' => 'not_configured'];
        }

        try {
            $res = Http::timeout(15)->post('https://vision.googleapis.com/v1/images:annotate?key='.$key, [
                'requests' => [[
                    'image' => ['content' => base64_encode($imageBytes)],
                    'features' => [['type' => 'DOCUMENT_TEXT_DETECTION']],
                ]],
            ]);
        } catch (\Throwable $e) {
            Log::error('PlateScanner: request failed.', ['error' => preg_replace('/key=[^&\s]+/', 'key=REDACTED', $e->getMessage())]);

            return ['ok' => false, 'reason' => 'service_error'];
        }

        if (! $res->ok()) {
            Log::error('PlateScanner: Vision API error.', ['status' => $res->status()]);

            return ['ok' => false, 'reason' => 'service_error'];
        }

        $resp = $res->json('responses.0') ?? [];
        $fullText = strtoupper((string) data_get($resp, 'fullTextAnnotation.text', ''));
        $state = $this->detectState($fullText);

        $best = null;
        foreach ($this->lines($resp) as $line) {
            $norm = preg_replace('/[^A-Z0-9]/', '', strtoupper($line['text']));
            $len = strlen($norm);
            if ($len < 5 || $len > 8 || ! preg_match('/\d/', $norm) || str_starts_with($norm, 'EXP')) {
                continue;
            }
            if ($best === null || $line['height'] > $best['height']
                || ($line['height'] === $best['height'] && $line['conf'] > $best['conf'])) {
                $best = ['plate' => $norm, 'height' => $line['height'], 'conf' => $line['conf']];
            }
        }

        if (! $best) {
            return ['ok' => false, 'reason' => 'no_plate_found', 'state' => $state];
        }

        return ['ok' => true, 'plate' => $best['plate'], 'state' => $state, 'confidence' => round($best['conf'], 2)];
    }

    /** Text lines with a height (bigger = more likely the plate number) and average word confidence. */
    private function lines(array $resp): array
    {
        $out = [];
        foreach (data_get($resp, 'fullTextAnnotation.pages', []) as $page) {
            foreach ($page['blocks'] ?? [] as $block) {
                foreach ($block['paragraphs'] ?? [] as $para) {
                    $text = '';
                    $height = 0;
                    $confs = [];
                    foreach ($para['words'] ?? [] as $word) {
                        $ys = array_column($word['boundingBox']['vertices'] ?? [], 'y');
                        if ($ys) {
                            $height = max($height, max($ys) - min($ys));
                        }
                        $confs[] = (float) ($word['confidence'] ?? 0);
                        foreach ($word['symbols'] ?? [] as $sym) {
                            $text .= $sym['text'] ?? '';
                            $break = $sym['property']['detectedBreak']['type'] ?? null;
                            if ($break === 'SPACE' || $break === 'SURE_SPACE') {
                                $text .= ' ';
                            } elseif ($break === 'EOL_SURE_SPACE' || $break === 'LINE_BREAK') {
                                $out[] = ['text' => $text, 'height' => $height, 'conf' => $confs ? array_sum($confs) / count($confs) : 0];
                                $text = '';
                                $height = 0;
                                $confs = [];
                            }
                        }
                    }
                    if (trim($text) !== '') {
                        $out[] = ['text' => $text, 'height' => $height, 'conf' => $confs ? array_sum($confs) / count($confs) : 0];
                    }
                }
            }
        }

        return $out;
    }

    private function detectState(string $text): ?string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        $names = array_keys(self::STATES);
        usort($names, fn ($a, $b) => strlen($b) <=> strlen($a)); // longest first: WEST VIRGINIA before VIRGINIA
        foreach ($names as $name) {
            if (preg_match('/\b'.preg_quote($name, '/').'\b/', $text)) {
                return self::STATES[$name];
            }
        }

        return null;
    }
}
