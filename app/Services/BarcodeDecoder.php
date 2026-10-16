<?php

namespace App\Services;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Decodes the PDF417 barcode on the back of an ID by running scripts/barcode_decode.py.
 *
 * Returns the RAW barcode text (feed it to your existing AAMVA parsing as $barcodeRaw).
 * The raw text is never logged here.
 */
class BarcodeDecoder
{
    /**
     * @param  string  $storagePath  path relative to the disk, e.g. "photo-ids/abc123.jpg"
     * @return array{text: ?string, reason: string, recipe: ?string}
     *         reason: ok | no_barcode | unreadable | timeout | bad_image | too_large
     *                 | not_configured | missing_file | unavailable | decoder_error
     */
    public function decodeStored(string $storagePath, string $disk = 'local'): array
    {
        $python = config('services.barcode_decoder.python');
        $script = config('services.barcode_decoder.script');
        $limit = (int) config('services.barcode_decoder.seconds', 6);

        if (! $python || ! $script || ! is_file($script)) {
            Log::warning('Barcode decoder not configured', ['python' => (bool) $python, 'script' => (bool) $script]);

            return $this->fail('not_configured');
        }

        $storage = Storage::disk($disk);
        if (! $storage->exists($storagePath)) {
            return $this->fail('missing_file');
        }

        try {
            // Arguments are passed as an array (no shell), and the path comes from our own storage disk.
            $result = Process::timeout($limit + 6)->run([$python, $script, $storage->path($storagePath), (string) $limit]);
        } catch (ProcessTimedOutException) {
            return $this->fail('timeout');
        } catch (Throwable $e) {
            // e.g. proc_open disabled by the host, or the interpreter path is wrong
            Log::warning('Barcode decoder unavailable', ['error' => $e->getMessage()]);

            return $this->fail('unavailable');
        }

        $json = json_decode(trim($result->output()), true);
        if (! is_array($json)) {
            Log::warning('Barcode decoder returned no JSON', [
                'exit' => $result->exitCode(),
                'stderr' => substr($result->errorOutput(), 0, 300),
            ]);

            return $this->fail('decoder_error');
        }

        if (! empty($json['ok']) && is_string($json['text'] ?? null) && $json['text'] !== '') {
            return ['text' => $json['text'], 'reason' => 'ok', 'recipe' => $json['recipe'] ?? null];
        }

        return $this->fail((string) ($json['reason'] ?? 'unknown'));
    }

    private function fail(string $reason): array
    {
        return ['text' => null, 'reason' => $reason, 'recipe' => null];
    }
}
