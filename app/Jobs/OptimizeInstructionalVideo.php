<?php

namespace App\Jobs;

use App\Models\InstructionalVideo;
use App\Services\VideoOptimizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class OptimizeInstructionalVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $video;
    public $originalFilePath;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @param InstructionalVideo $video
     * @param string $originalFilePath The exact storage path that was uploaded to prevent race conditions.
     */
    public function __construct(InstructionalVideo $video, string $originalFilePath)
    {
        $this->video = $video;
        $this->originalFilePath = $originalFilePath;
    }

    /**
     * Execute the job.
     */
    public function handle(VideoOptimizer $optimizer): void
    {
        // 1. Verify the database record still exists and points to this exact file.
        // This prevents overwriting a new video if the user rapidly updated the same record.
        $this->video->refresh();
        if ($this->video->video_file_path !== $this->originalFilePath) {
            Log::info("Skipping video optimization for {$this->originalFilePath}: The database record now points to a different file.");
            return;
        }

        // 2. Verify the source file still exists on the public disk.
        if (!Storage::disk('public')->exists($this->originalFilePath)) {
            Log::warning("Skipping video optimization for {$this->originalFilePath}: The file no longer exists on the disk.");
            return;
        }

        $absoluteSourcePath = Storage::disk('public')->path($this->originalFilePath);
        $absoluteBackupPath = $absoluteSourcePath . '.bak_' . uniqid();

        try {
            // 3. Run the optimizer (returns the absolute path of the valid temporary optimized file)
            $absoluteOptimizedPath = $optimizer->optimizeForFastStart($absoluteSourcePath);

            // 4. Safely replace the original file
            // Move original to backup
            if (!rename($absoluteSourcePath, $absoluteBackupPath)) {
                @unlink($absoluteOptimizedPath);
                throw new Exception("Failed to rename original file to backup path.");
            }

            // Move optimized into original location
            if (!rename($absoluteOptimizedPath, $absoluteSourcePath)) {
                // If it fails, restore from backup immediately
                rename($absoluteBackupPath, $absoluteSourcePath);
                @unlink($absoluteOptimizedPath);
                throw new Exception("Failed to move optimized file into original location. Backup restored.");
            }

            // 5. Verify the replacement was successful
            if (!file_exists($absoluteSourcePath) || filesize($absoluteSourcePath) === 0) {
                rename($absoluteBackupPath, $absoluteSourcePath);
                throw new Exception("Optimized file replacement resulted in missing or empty file. Backup restored.");
            }

            // 6. Delete the temporary backup
            @unlink($absoluteBackupPath);

            Log::info("Successfully optimized video for fast-start: {$this->originalFilePath}");

        } catch (Exception $e) {
            Log::error("Failed to optimize video for fast-start: {$this->originalFilePath}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Ensure backup is restored if it was left dangling
            if (file_exists($absoluteBackupPath) && !file_exists($absoluteSourcePath)) {
                rename($absoluteBackupPath, $absoluteSourcePath);
            }

            // Let the queue system handle retries if applicable
            throw $e;
        }
    }
}
