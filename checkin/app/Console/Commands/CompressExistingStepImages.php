<?php

namespace App\Console\Commands;

use App\Models\InstructionStep;
use App\Models\InstructionStepImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class CompressExistingStepImages extends Command
{
    protected $signature = 'images:compress-existing';
    protected $description = 'Resize and compress instruction step images that were uploaded before automatic compression was added.';

    public function handle(): int
    {
        $driverClasses = [];
        if (extension_loaded('imagick')) {
            $driverClasses[] = \Intervention\Image\Drivers\Imagick\Driver::class;
        }
        if (extension_loaded('gd')) {
            $driverClasses[] = \Intervention\Image\Drivers\Gd\Driver::class;
        }

        if (empty($driverClasses)) {
            $this->error('No usable image driver (Imagick or GD) found. Aborting.');
            return self::FAILURE;
        }

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        $rows = [];
        foreach (InstructionStep::whereNotNull('image_path')->get() as $step) {
            $rows[] = ['model' => $step, 'field' => 'image_path'];
        }
        foreach (InstructionStepImage::whereNotNull('image_path')->get() as $img) {
            $rows[] = ['model' => $img, 'field' => 'image_path'];
        }

        $this->info('Found ' . count($rows) . ' image(s) to check.');

        foreach ($rows as $row) {
            $model = $row['model'];
            $path = $model->{$row['field']};

            if (!Storage::disk('public')->exists($path)) {
                $this->warn("Skipping missing file: {$path}");
                $skipped++;
                continue;
            }

            $currentSize = Storage::disk('public')->size($path);

            if ($currentSize < 300 * 1024) {
                $skipped++;
                continue;
            }

            $resized = false;
            foreach ($driverClasses as $driverClass) {
                try {
                    $manager = new ImageManager(new $driverClass());
                    $fullPath = Storage::disk('public')->path($path);
                    $image = $manager->read($fullPath);
                    $image->scaleDown(width: 1600);

                    $directory = dirname($path);
                    $newFilename = $directory . '/' . uniqid() . '.jpg';
                    $encoded = $image->toJpeg(75);

                    Storage::disk('public')->put($newFilename, (string) $encoded);

                    $oldPath = $path;
                    $model->{$row['field']} = $newFilename;
                    $model->save();
                    // Original file intentionally left in place, not deleted.
                    // Other records (media library, categories, amenities, etc.)
                    // may still reference the same original path, so deleting
                    // it here could break something unrelated. Leftover old
                    // files can be cleaned up separately later if needed.

                    $newSize = Storage::disk('public')->size($newFilename);
                    $this->info("Compressed {$oldPath}: " . round($currentSize / 1024) . "KB -> " . round($newSize / 1024) . "KB");

                    $resized = true;
                    $processed++;
                    break;
                } catch (\Throwable $e) {
                    Log::warning('Backfill compress failed, trying next driver.', [
                        'path' => $path,
                        'driver' => $driverClass,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            if (!$resized) {
                $this->error("Failed to compress: {$path}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Compressed: {$processed}, Skipped (already small/missing): {$skipped}, Failed: {$failed}");

        return self::SUCCESS;
    }
}
