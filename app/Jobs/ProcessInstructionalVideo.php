<?php

namespace App\Jobs;

use App\Models\InstructionalVideo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessInstructionalVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900;
    public $tries = 1;

    protected $video;

    public function __construct(InstructionalVideo $video)
    {
        $this->video = $video;
    }

    private function setProgress(int $pct): void
    {
        Cache::put('video_progress:' . $this->video->id, max(0, min(100, $pct)), now()->addHours(2));
    }

    public function handle(): void
    {
        set_time_limit(900);
        ignore_user_abort(true);
        $this->video->update(['processing_status' => 'processing']);
        $this->setProgress(0);
        Cache::forget('video_error:' . $this->video->id);

        $disk = Storage::disk('public');
        $rel = $this->video->video_file_path;
        $newPath = null;

        try {
            if (! $rel || ! $disk->exists($rel)) {
                throw new \Exception('Input file does not exist on public disk: ' . $rel);
            }

            $src = $disk->path($rel);
            $info = $this->probe($src);

            // Already web-safe (H.264 + yuv420p + AAC or silent)? Just remux, which takes about a second.
            $compatible = $info['video'] === 'h264'
                && $info['pix_fmt'] === 'yuv420p'
                && ($info['audio'] === null || $info['audio'] === 'aac');

            $name = pathinfo($rel, PATHINFO_FILENAME);
            $newPath = 'videos/' . preg_replace('/_web_\d+$/', '', $name) . '_web_' . time() . '.mp4';
            $dest = $disk->path($newPath);

            $args = [config('services.ffmpeg.binary'), '-y', '-nostats', '-progress', 'pipe:1', '-i', $src, '-map', '0:v:0', '-map', '0:a:0?'];
            if ($compatible) {
                $args = array_merge($args, ['-c', 'copy']);
            } else {
                $args = array_merge($args, [
                    '-vf', "scale='min(1280,iw)':-2",
                    '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '23',
                    '-profile:v', 'main', '-pix_fmt', 'yuv420p',
                    '-c:a', 'aac', '-b:a', '128k',
                ]);
            }
            $args = array_merge($args, ['-movflags', '+faststart', $dest]);

            Log::info('VIDEO_PROCESS_START', ['id' => $this->video->id, 'mode' => $compatible ? 'remux' : 'transcode', 'probe' => $info]);

            $duration = max(0.1, (float) $info['duration']);
            $last = 0;
            $result = Process::timeout(840)->run($args, function (string $type, string $output) use ($duration, &$last) {
                if (preg_match_all('/out_time_(?:us|ms)=(\d+)/', $output, $m) && ! empty($m[1])) {
                    $seconds = ((float) end($m[1])) / 1000000;
                    $pct = (int) min(99, floor(($seconds / $duration) * 100));
                    if ($pct > $last) {
                        $last = $pct;
                        $this->setProgress($pct);
                    }
                }
            });

            if ($result->failed()) {
                Log::error('VIDEO_FFMPEG_FAILED', ['id' => $this->video->id, 'stderr' => substr($result->errorOutput(), -2000)]);
                throw new \Exception('ffmpeg failed with exit code ' . $result->exitCode());
            }
            if (! $disk->exists($newPath) || $disk->size($newPath) === 0) {
                throw new \Exception('ffmpeg produced no output file.');
            }

            if ($rel !== $newPath && $disk->exists($rel)) {
                $disk->delete($rel);
            }
            $this->video->update(['video_file_path' => $newPath, 'processing_status' => 'completed']);
            $this->setProgress(100);
            Log::info('VIDEO_PROCESS_DONE', ['id' => $this->video->id, 'path' => $newPath]);
        } catch (Throwable $e) {
            if ($newPath && $disk->exists($newPath)) {
                $disk->delete($newPath);
            }
            $this->video->update(['processing_status' => 'failed']);
            Cache::put('video_error:' . $this->video->id, $e->getMessage(), now()->addHours(2));
            Log::error('VIDEO_PROCESS_ERROR', ['id' => $this->video->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function probe(string $src): array
    {
        $r = Process::timeout(60)->run([
            'ffprobe', '-v', 'error', '-show_entries', 'stream=codec_type,codec_name,pix_fmt:format=duration', '-of', 'json', $src,
        ]);
        $out = ['video' => null, 'pix_fmt' => null, 'audio' => null, 'duration' => 0];
        if ($r->failed()) {
            return $out; // unknown, so the caller transcodes
        }
        $data = json_decode($r->output(), true) ?: [];
        $out['duration'] = (float) ($data['format']['duration'] ?? 0);
        foreach (($data['streams'] ?? []) as $s) {
            if (($s['codec_type'] ?? '') === 'video' && $out['video'] === null) {
                $out['video'] = $s['codec_name'] ?? null;
                $out['pix_fmt'] = $s['pix_fmt'] ?? null;
            } elseif (($s['codec_type'] ?? '') === 'audio' && $out['audio'] === null) {
                $out['audio'] = $s['codec_name'] ?? null;
            }
        }
        return $out;
    }
}
