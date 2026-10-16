<?php

namespace App\Jobs;

use App\Models\TaskMedia;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Converts an uploaded task/room/property video to an iPhone-safe MP4
 * (H.264 + AAC, faststart). Leaves the original untouched if anything fails.
 */
class ConvertTaskVideo
{
    use Dispatchable;

    public function __construct(public string $path)
    {
    }

    public function handle(): void
    {
        set_time_limit(900);
        ignore_user_abort(true);

        $disk = Storage::disk('public');
        $rel = $this->path;
        $newPath = null;

        try {
            if (! $rel || ! $disk->exists($rel)) {
                return;
            }
            $name = pathinfo($rel, PATHINFO_FILENAME);
            if (str_ends_with($name, '_web')) {
                return; // already converted
            }
            if (Process::run([config('services.ffmpeg.binary'), '-version'])->failed()) {
                Log::warning('VIDEO_CONVERT_SKIPPED: ffmpeg is not available', ['path' => $rel]);
                return;
            }

            $src = $disk->path($rel);
            $info = $this->probe($src);
            $compatible = $info['video'] === 'h264'
                && $info['pix_fmt'] === 'yuv420p'
                && ($info['audio'] === null || $info['audio'] === 'aac');

            $dir = trim(dirname($rel), './');
            $newPath = ($dir !== '' ? $dir.'/' : '').$name.'_web.mp4';
            $dest = $disk->path($newPath);

            $args = [config('services.ffmpeg.binary'), '-y', '-nostats', '-i', $src, '-map', '0:v:0', '-map', '0:a:0?'];
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

            $result = Process::timeout(840)->run($args);
            if ($result->failed() || ! $disk->exists($newPath) || $disk->size($newPath) === 0) {
                throw new \Exception('ffmpeg failed: '.substr($result->errorOutput(), -800));
            }

            // Point every row that used the original (clones share the file) at the new one.
            $updated = TaskMedia::where('url', $rel)->update(['url' => $newPath]);
            if ($updated === 0) {
                $disk->delete($newPath); // media was deleted while we converted
                return;
            }
            $disk->delete($rel);
            Log::info('VIDEO_CONVERT_DONE', ['from' => $rel, 'to' => $newPath, 'rows' => $updated, 'mode' => $compatible ? 'remux' : 'transcode']);
        } catch (Throwable $e) {
            if ($newPath && $disk->exists($newPath)) {
                $disk->delete($newPath);
            }
            Log::error('VIDEO_CONVERT_ERROR', ['path' => $rel, 'error' => $e->getMessage()]);
        }
    }

    private function probe(string $src): array
    {
        $out = ['video' => null, 'pix_fmt' => null, 'audio' => null];
        $r = Process::timeout(60)->run(['ffprobe', '-v', 'error', '-show_entries', 'stream=codec_type,codec_name,pix_fmt', '-of', 'json', $src]);
        if ($r->failed()) {
            return $out; // unknown, so we transcode
        }
        foreach ((json_decode($r->output(), true)['streams'] ?? []) as $s) {
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
