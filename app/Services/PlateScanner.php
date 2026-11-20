<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads a license plate (text + state), make/model and distance feedback from a photo using
 * the Google Vision key already configured for ID scanning. The image is never stored here.
 * Characters are NOT auto-corrected (O/0, I/1); the guest confirms the result.
 */
class PlateScanner
{
    /** Plate character height as a share of the frame's longer side. Below this the guest is too far away. Tune from the logs. */
    private const MIN_CHAR_RATIO = 0.015;

    /** A vehicle must cover at least this share of the frame. */
    private const MIN_VEHICLE_AREA = 0.2;

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

    /** Slogans printed on plates, used only when the state name itself is unreadable. */
    private const SLOGANS = [
        'SUNSHINE STATE' => 'FL', 'GOLDEN STATE' => 'CA', 'LONE STAR' => 'TX', 'GARDEN STATE' => 'NJ',
        'PEACH STATE' => 'GA', 'LAND OF LINCOLN' => 'IL', 'PALMETTO' => 'SC', 'LIVE FREE OR DIE' => 'NH',
        'FIRST STATE' => 'DE',
    ];

    private const MAKES = [
        'MERCEDES-BENZ', 'VOLKSWAGEN', 'CHEVROLET', 'MITSUBISHI', 'LAND ROVER', 'INFINITI', 'CHRYSLER', 'CADILLAC',
        'HYUNDAI', 'MERCEDES', 'PORSCHE', 'JAGUAR', 'GENESIS', 'SUBARU', 'LINCOLN', 'NISSAN', 'TOYOTA', 'RIVIAN',
        'VOLVO', 'LEXUS', 'ACURA', 'BUICK', 'DODGE', 'HONDA', 'MAZDA', 'TESLA', 'CHEVY', 'AUDI', 'FORD', 'JEEP',
        'FIAT', 'BMW', 'GMC', 'KIA', 'RAM', 'MINI',
    ];

    private const MAKE_DISPLAY = ['BMW' => 'BMW', 'GMC' => 'GMC', 'CHEVY' => 'Chevrolet'];

    /** Badge text that is a trim/feature, not a model name. */
    private const NOT_MODELS = [
        'HYBRID', 'AWD', '4WD', '4X4', 'LED', 'TURBO', 'SPORT', 'LIMITED', 'PREMIUM', 'LUXURY', 'ECOBOOST', 'HEMI',
        'DIESEL', 'PLUS', 'LX', 'EX', 'SE', 'LE', 'XLE', 'SR5', '4MATIC', 'XDRIVE', 'QUATTRO', 'AUTO', 'EXP',
    ];

    private const VEHICLE_OBJECTS = ['CAR', 'VAN', 'TRUCK', 'BUS', 'VEHICLE', 'SUV', 'TAXI', 'LAND VEHICLE'];

    /**
     * @param  bool  $live  true for the cheap per-second coaching frames (no logo detection, no crop pass)
     */
    public function scan(string $imageBytes, bool $live = false): array
    {
        $key = config('services.google_vision.key');
        if (blank($key)) {
            return ['ok' => false, 'reason' => 'not_configured', 'hint' => 'unavailable'];
        }

        $features = [['type' => 'DOCUMENT_TEXT_DETECTION'], ['type' => 'OBJECT_LOCALIZATION', 'maxResults' => 20]];
        if (! $live) {
            $features[] = ['type' => 'LOGO_DETECTION', 'maxResults' => 3];
        }

        $resp = $this->annotate($key, $imageBytes, $features, $live ? 8 : 15);
        if ($resp === null) {
            return ['ok' => false, 'reason' => 'service_error', 'hint' => 'unavailable'];
        }

        [$imgW, $imgH] = $this->dims($imageBytes);
        $fullText = strtoupper((string) data_get($resp, 'fullTextAnnotation.text', ''));
        $state = $this->detectState($fullText);
        $vehicle = $this->vehicle($resp);
        $lines = $this->lines($resp);
        $best = $this->bestPlate($lines);

        if (! $live && ! $state && $best && $vehicle['ok'] && $imgW > 0) {
            $state = $this->stateFromCrop($key, $imageBytes, $best['box'], $imgW, $imgH);
        }

        $ratio = ($best && $imgW > 0 && $imgH > 0) ? $best['height'] / max($imgW, $imgH) : null;
        $hint = $this->hint($vehicle, $best, $state, $ratio);
        $makeModel = $live ? null : $this->makeModel($resp, $fullText, $lines, $best);

        if (! $live) {
            Log::info('PlateScanner: scan', [
                'plate' => $best['plate'] ?? null, 'state' => $state, 'hint' => $hint, 'make_model' => $makeModel,
                'char_ratio' => $ratio !== null ? round($ratio, 4) : null, 'vehicle_area' => round($vehicle['area'], 2),
                'vehicle_cut' => $vehicle['cut'],
            ]);
        }

        $base = ['hint' => $hint, 'state' => $state, 'make_model' => $makeModel];

        if (! $vehicle['ok']) {
            return ['ok' => false, 'reason' => 'not_whole_vehicle'] + $base;
        }
        if (! $best) {
            return ['ok' => false, 'reason' => 'no_plate_found'] + $base;
        }

        return ['ok' => true, 'plate' => $best['plate'], 'confidence' => round($best['conf'], 2)] + $base;
    }

    private function annotate(string $key, string $bytes, array $features, int $timeout = 15): ?array
    {
        try {
            $res = Http::timeout($timeout)->post('https://vision.googleapis.com/v1/images:annotate?key='.$key, [
                'requests' => [['image' => ['content' => base64_encode($bytes)], 'features' => $features]],
            ]);
        } catch (\Throwable $e) {
            Log::error('PlateScanner: request failed.', ['error' => preg_replace('/key=[^&\s]+/', 'key=REDACTED', $e->getMessage())]);

            return null;
        }

        if (! $res->ok()) {
            Log::error('PlateScanner: Vision API error.', ['status' => $res->status()]);

            return null;
        }

        return $res->json('responses.0') ?? [];
    }

    private function dims(string $bytes): array
    {
        $i = @getimagesizefromstring($bytes);

        return $i ? [(int) $i[0], (int) $i[1]] : [0, 0];
    }

    /** Distance/framing instruction for the guest. */
    private function hint(array $v, ?array $best, ?string $state, ?float $ratio): string
    {
        if (! $v['found']) {
            return 'no_vehicle';
        }
        if ($v['cut']) {
            return 'move_back';
        }
        if ($v['area'] < self::MIN_VEHICLE_AREA) {
            return 'move_closer';
        }
        if (! $best) {
            return $v['area'] < 0.35 ? 'move_closer' : 'plate_unclear';
        }
        if ($ratio !== null && $ratio < self::MIN_CHAR_RATIO) {
            return 'move_closer';
        }
        if (! $state) {
            return 'state_unclear';
        }

        return 'ok';
    }

    /** Text lines, each with its words (height, confidence, box). */
    private function lines(array $resp): array
    {
        $out = [];
        foreach (data_get($resp, 'fullTextAnnotation.pages', []) as $page) {
            foreach ($page['blocks'] ?? [] as $block) {
                foreach ($block['paragraphs'] ?? [] as $para) {
                    $cur = [];
                    foreach ($para['words'] ?? [] as $word) {
                        $vs = $word['boundingBox']['vertices'] ?? [];
                        $xs = array_map(fn ($p) => (int) ($p['x'] ?? 0), $vs);
                        $ys = array_map(fn ($p) => (int) ($p['y'] ?? 0), $vs);
                        if (! $xs || ! $ys) {
                            continue;
                        }
                        $text = '';
                        $first = null;
                        $break = null;
                        foreach ($word['symbols'] ?? [] as $sym) {
                            $text .= $sym['text'] ?? '';
                            if ($first === null) {
                                $first = (float) ($sym['confidence'] ?? 1);
                            }
                            $break = $sym['property']['detectedBreak']['type'] ?? null;
                        }
                        $cur[] = [
                            'text' => $text,
                            'h' => max($ys) - min($ys),
                            'conf' => (float) ($word['confidence'] ?? 0),
                            'box' => [min($xs), min($ys), max($xs), max($ys)],
                            'first_conf' => $first ?? 1.0,
                        ];
                        if (in_array($break, ['EOL_SURE_SPACE', 'LINE_BREAK'], true)) {
                            $out[] = $this->makeLine($cur);
                            $cur = [];
                        }
                    }
                    if ($cur) {
                        $out[] = $this->makeLine($cur);
                    }
                }
            }
        }

        return $out;
    }

    private function makeLine(array $words): array
    {
        $confs = array_column($words, 'conf');

        return [
            'text' => implode(' ', array_column($words, 'text')),
            'height' => max(array_column($words, 'h')),
            'conf' => $confs ? array_sum($confs) / count($confs) : 0,
            'box' => $this->union(array_column($words, 'box')),
            'words' => $words,
        ];
    }

    private function union(array $boxes): array
    {
        return [
            min(array_column($boxes, 0)), min(array_column($boxes, 1)),
            max(array_column($boxes, 2)), max(array_column($boxes, 3)),
        ];
    }

    private function bestPlate(array $lines): ?array
    {
        $best = null;
        foreach ($lines as $line) {
            $cand = $this->plateFromWords($line['words']);
            if (! $cand) {
                continue;
            }
            if ($best === null || $cand['height'] > $best['height']
                || ($cand['height'] === $best['height'] && $cand['conf'] > $best['conf'])) {
                $best = $cand;
            }
        }

        return $best;
    }

    /**
     * Builds a plate candidate from one line's words. Drops small print (state name, slogans,
     * stickers) and stray single characters at the edges (the "extra leading letter").
     */
    private function plateFromWords(array $words): ?array
    {
        if (! $words) {
            return null;
        }
        $maxH = max(array_column($words, 'h'));
        $kept = [];
        foreach ($words as $w) {
            $t = preg_replace('/[^A-Z0-9]/', '', strtoupper($w['text']));
            if ($t === '') {
                continue;
            }
            if ($maxH > 0 && $w['h'] < $maxH * 0.7) {
                continue;
            }
            $kept[] = $w + ['t' => $t];
        }
        if (! $kept) {
            return null;
        }
        usort($kept, fn ($a, $b) => $a['box'][0] <=> $b['box'][0]);

        while (count($kept) > 1 && $this->isStray($kept[0], $kept[1]['box'][0] - $kept[0]['box'][2])) {
            array_shift($kept);
        }
        while (count($kept) > 1) {
            $n = count($kept);
            if (! $this->isStray($kept[$n - 1], $kept[$n - 1]['box'][0] - $kept[$n - 2]['box'][2])) {
                break;
            }
            array_pop($kept);
        }

        $norm = implode('', array_column($kept, 't'));
        if (strlen($norm) >= 7 && $kept[0]['first_conf'] < 0.5) {
            $norm = substr($norm, 1); // first glyph was a shaky read on a long result: likely not part of the plate
        }

        $len = strlen($norm);
        if ($len < 5 || $len > 8 || ! preg_match('/\d/', $norm) || str_starts_with($norm, 'EXP')) {
            return null;
        }

        $confs = array_column($kept, 'conf');

        return [
            'plate' => $norm,
            'height' => max(array_column($kept, 'h')),
            'conf' => array_sum($confs) / count($confs),
            'box' => $this->union(array_column($kept, 'box')),
        ];
    }

    private function isStray(array $w, int $gap): bool
    {
        return strlen($w['t']) === 1 && ($w['conf'] < 0.75 || $gap > 1.2 * max(1, $w['h']));
    }

    /** Vehicle in frame: found, largest coverage, cut-off flag, and ok = a whole vehicle of enough size. */
    private function vehicle(array $resp): array
    {
        $out = ['found' => false, 'area' => 0.0, 'cut' => false, 'ok' => false];
        foreach (data_get($resp, 'localizedObjectAnnotations', []) as $o) {
            if (! in_array(strtoupper($o['name'] ?? ''), self::VEHICLE_OBJECTS, true)) {
                continue;
            }
            $v = $o['boundingPoly']['normalizedVertices'] ?? [];
            if (count($v) < 3) {
                continue;
            }
            $xs = array_map(fn ($pt) => (float) ($pt['x'] ?? 0), $v);
            $ys = array_map(fn ($pt) => (float) ($pt['y'] ?? 0), $v);
            $area = (max($xs) - min($xs)) * (max($ys) - min($ys));
            $cut = min($xs) < 0.01 || max($xs) > 0.99 || min($ys) < 0.01 || max($ys) > 0.99;

            if ($area >= self::MIN_VEHICLE_AREA && ! $cut) {
                $out['ok'] = true;
            }
            if ($area > $out['area']) {
                $out['found'] = true;
                $out['area'] = $area;
                $out['cut'] = $cut;
            }
        }
        if ($out['ok']) {
            $out['cut'] = false;
        }

        return $out;
    }

    private function detectState(string $text): ?string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        foreach ([self::STATES, self::SLOGANS] as $map) {
            $names = array_keys($map);
            usort($names, fn ($a, $b) => strlen($b) <=> strlen($a)); // longest first: WEST VIRGINIA before VIRGINIA
            foreach ($names as $name) {
                if (preg_match('/\b'.preg_quote($name, '/').'\b/', $text)) {
                    return $map[$name];
                }
            }
        }

        return null;
    }

    /** Second pass: crop around the plate, upscale, and re-read so small state names become legible. */
    private function stateFromCrop(string $key, string $bytes, array $box, int $w, int $h): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagecrop') || ! function_exists('imagescale')) {
            return null;
        }
        $bw = max(1, $box[2] - $box[0]);
        $bh = max(1, $box[3] - $box[1]);
        $x = max(0, (int) round($box[0] - $bw * 0.4));
        $y = max(0, (int) round($box[1] - $bh * 3.2));
        $x2 = min($w, (int) round($box[2] + $bw * 0.4));
        $y2 = min($h, (int) round($box[3] + $bh * 2.2));
        if ($x2 - $x < 40 || $y2 - $y < 40) {
            return null;
        }

        $im = @imagecreatefromstring($bytes);
        if (! $im) {
            return null;
        }
        $crop = imagecrop($im, ['x' => $x, 'y' => $y, 'width' => $x2 - $x, 'height' => $y2 - $y]);
        if (! $crop) {
            return null;
        }
        if (imagesx($crop) < 1400) {
            $scaled = imagescale($crop, 1400, -1, IMG_BICUBIC);
            if ($scaled) {
                $crop = $scaled;
            }
        }
        ob_start();
        imagejpeg($crop, null, 90);
        $jpg = (string) ob_get_clean();
        if ($jpg === '') {
            return null;
        }

        $r = $this->annotate($key, $jpg, [['type' => 'DOCUMENT_TEXT_DETECTION']]);

        return $r ? $this->detectState(strtoupper((string) data_get($r, 'fullTextAnnotation.text', ''))) : null;
    }

    /** "Toyota Camry" style guess. The model is only guessed when a make was found; the guest always confirms. */
    private function makeModel(array $resp, string $fullText, array $lines, ?array $best): ?string
    {
        $makes = self::MAKES; // already longest-first within each group
        $make = null;
        foreach (data_get($resp, 'logoAnnotations', []) as $l) {
            $d = strtoupper((string) ($l['description'] ?? ''));
            foreach ($makes as $m) {
                if (preg_match('/\b'.preg_quote($m, '/').'\b/', $d)) {
                    $make = $m;
                    break 2;
                }
            }
        }
        if (! $make) {
            foreach ($makes as $m) {
                if (in_array($m, ['RAM', 'MINI'], true)) {
                    continue;
                }
                if (preg_match('/\b'.preg_quote($m, '/').'\b/', $fullText)) {
                    $make = $m;
                    break;
                }
            }
        }
        if (! $make) {
            return null;
        }

        $model = null;
        $modelH = 0;
        foreach ($lines as $line) {
            $t = trim(preg_replace('/\s+/', ' ', strtoupper($line['text'])));
            $len = strlen($t);
            if ($len < 2 || $len > 12 || substr_count($t, ' ') > 1) {
                continue;
            }
            if (! preg_match('/^[A-Z0-9][A-Z0-9\- ]*$/', $t) || ! preg_match('/[A-Z]/', $t)) {
                continue;
            }
            if (str_contains($t, $make) || in_array($t, self::NOT_MODELS, true) || isset(self::STATES[$t])) {
                continue;
            }
            if ($best && preg_replace('/[^A-Z0-9]/', '', $t) === $best['plate']) {
                continue;
            }
            if ($line['conf'] < 0.7 || ($best && $line['height'] < $best['height'] * 0.4)) {
                continue;
            }
            if ($line['height'] > $modelH) {
                $modelH = $line['height'];
                $model = $t;
            }
        }

        $makeLabel = self::MAKE_DISPLAY[$make] ?? ucwords(strtolower($make), ' -');
        if (! $model) {
            return $makeLabel;
        }
        $modelLabel = (preg_match('/\d/', $model) || strlen($model) <= 3) ? $model : ucfirst(strtolower($model));

        return $makeLabel.' '.$modelLabel;
    }
}
