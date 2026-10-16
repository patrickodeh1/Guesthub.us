<?php

namespace App\Console\Commands;

use App\Jobs\ConvertTaskVideo;
use App\Models\TaskMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConvertStoredVideos extends Command
{
    protected $signature = 'videos:convert {--dry-run : List what would be converted without changing anything}';
    protected $description = 'Convert already-uploaded task/room/property videos to iPhone-safe MP4';

    public function handle(): int
    {
        $paths = DB::table((new TaskMedia)->getTable())->where('type', 'video')->distinct()->pluck('url')
            ->map(fn ($u) => TaskMedia::normalizePath($u))->filter()->unique();

        $todo = $paths->filter(fn ($p) => ! str_ends_with(pathinfo($p, PATHINFO_FILENAME), '_web') && Storage::disk('public')->exists($p));
        $this->info($todo->count().' video(s) to convert, '.($paths->count() - $todo->count()).' skipped (already converted or missing).');

        foreach ($todo as $p) {
            $this->line(($this->option('dry-run') ? 'would convert ' : 'converting ').$p);
            if (! $this->option('dry-run')) {
                ConvertTaskVideo::dispatchSync($p);
            }
        }
        return self::SUCCESS;
    }
}
