<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class VideoOptimizer
{
    /**
     * Optimizes an MP4 video for fast-start web playback.
     * 
     * @param string $sourcePath The absolute path to the source video file.
     * @return string The absolute path to the optimized temporary video file.
     * @throws Exception If FFmpeg fails or the output is invalid.
     */
    public function optimizeForFastStart(string $sourcePath): string
    {
        if (!file_exists($sourcePath)) {
            throw new Exception("Source video file does not exist: {$sourcePath}");
        }

        // Allow ffmpeg.exe in the project root to override system ffmpeg
        $ffmpegPath = 'ffmpeg';
        if (file_exists(base_path('ffmpeg.exe'))) {
            $ffmpegPath = base_path('ffmpeg.exe');
        }

        // Verify FFmpeg is available
        $ffmpegCheck = Process::run([$ffmpegPath, '-version']);
        if ($ffmpegCheck->failed()) {
            throw new Exception("FFmpeg is not installed or accessible by the PHP process. Optimization cannot proceed. Checked path: {$ffmpegPath}");
        }

        // Generate temporary output path safely in the same directory if possible
        $directory = dirname($sourcePath);
        $tempFilename = 'optimized_' . uniqid() . '_' . basename($sourcePath);
        $tempOutputPath = $directory . DIRECTORY_SEPARATOR . $tempFilename;

        // Execute FFmpeg safely using Process facade to prevent shell injection
        // We enforce H.264 (libx264), yuv420p pixel format, and AAC audio for maximum iOS Safari compatibility.
        // -movflags +faststart moves the moov atom to the beginning for streaming.
        $process = Process::timeout(1800) // 30 minutes max for massive files
            ->run([
                $ffmpegPath,
                '-y', // Overwrite output if it exists
                '-i', $sourcePath,
                '-c:v', 'libx264',
                '-preset', 'fast',
                '-crf', '26', // Good balance of quality and size
                '-profile:v', 'main', // Highly compatible profile
                '-pix_fmt', 'yuv420p', // Required for many Apple devices
                '-c:a', 'aac',
                '-b:a', '128k',
                '-movflags', '+faststart',
                $tempOutputPath
            ]);

        if ($process->failed()) {
            Log::error('FFmpeg optimization failed', [
                'source' => $sourcePath,
                'exit_code' => $process->exitCode(),
                'error_output' => $process->errorOutput()
            ]);
            
            // Clean up any partially created temporary file
            if (file_exists($tempOutputPath)) {
                @unlink($tempOutputPath);
            }
            
            throw new Exception("FFmpeg process failed with exit code {$process->exitCode()}.");
        }

        // Validate the output file
        if (!file_exists($tempOutputPath)) {
            throw new Exception("FFmpeg succeeded but the output file was not created.");
        }

        if (!is_readable($tempOutputPath)) {
            @unlink($tempOutputPath);
            throw new Exception("FFmpeg created the output file but it is not readable.");
        }

        if (filesize($tempOutputPath) === 0) {
            @unlink($tempOutputPath);
            throw new Exception("FFmpeg created an empty 0-byte file.");
        }

        return $tempOutputPath;
    }
}
