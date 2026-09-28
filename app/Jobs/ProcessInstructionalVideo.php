<?php

namespace App\Jobs;

use App\Models\InstructionalVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessInstructionalVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The maximum number of seconds the job can run before timing out.
     */
    public $timeout = 3600; // 1 hour for large videos

    protected $video;

    /**
     * Create a new job instance.
     */
    public function __construct(InstructionalVideo $video)
    {
        $this->video = $video;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Prevent PHP from timing out during synchronous execution
        set_time_limit(3600);
        
        \Log::info('FFMPEG_PROCESS_STARTING', ['video_id' => $this->video->id, 'path' => $this->video->video_file_path]);
        $this->video->update(['processing_status' => 'processing']);

        try {
            // Verify input file exists
            if (!Storage::disk('public')->exists($this->video->video_file_path)) {
                throw new \Exception("Input file does not exist on public disk: " . $this->video->video_file_path);
            }

            $format = new X264('aac');
            $format->setAdditionalParameters(['-movflags', '+faststart']);

            $pathInfo = pathinfo($this->video->video_file_path);
            // Ensure the new filename is unique
            $newFileName = $pathInfo['filename'] . '_converted_' . time() . '.mp4';
            $newPath = 'videos/' . $newFileName;

            // Ensure output directory exists conceptually
            $absoluteOutputPath = Storage::disk('public')->path($newPath);
            $outputDir = dirname($absoluteOutputPath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            FFMpeg::fromDisk('public')
                ->open($this->video->video_file_path)
                ->export()
                ->toDisk('public')
                ->inFormat($format)
                ->save($newPath);

            // Verify output file
            if (!Storage::disk('public')->exists($newPath)) {
                throw new \Exception("FFmpeg succeeded but output file is missing.");
            }
            if (Storage::disk('public')->size($newPath) === 0) {
                Storage::disk('public')->delete($newPath);
                throw new \Exception("FFmpeg created an empty 0-byte file.");
            }

            \Log::info('FFMPEG_PROCESS_COMPLETED', ['video_id' => $this->video->id, 'output_path' => $newPath]);

            // Delete original file
            if (Storage::disk('public')->exists($this->video->video_file_path)) {
                Storage::disk('public')->delete($this->video->video_file_path);
            }

            // Update video model
            $this->video->update([
                'video_file_path' => $newPath,
                'processing_status' => 'completed',
            ]);
            \Log::info('FINAL_VIDEO_STORED', ['video_id' => $this->video->id]);

        } catch (\ProtoneMedia\LaravelFFMpeg\Exceptions\EncodingException $e) {
            \Log::error('FFMPEG_ENCODING_ERROR', [
                'video_id' => $this->video->id,
                'command' => $e->getCommand(),
                'error_output' => $e->getErrorOutput(),
            ]);
            $this->video->update(['processing_status' => 'failed']);
            throw $e;
        } catch (Throwable $e) {
            \Log::error('FFMPEG_PROCESS_ERROR', [
                'video_id' => $this->video->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->video->update(['processing_status' => 'failed']);
            throw $e;
        }
    }
}
