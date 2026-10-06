<?php

namespace App\Console\Commands;

use App\Models\ChecklistItemPhoto;
use App\Models\RoomPhoto;
use App\Services\PersistentPhotoStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneOldSessionPhotos extends Command
{
    protected $signature = 'photos:prune-old {--days=15 : Days to keep photos} {--dry-run : List only}';
    protected $description = 'Permanently delete cleaner session photos older than N days';

    // Never delete files under these folders, whatever a DB row says.
    private const PROTECTED_PREFIXES = [
        'brand/', 'task-media/', 'logos/', 'branding/', 'category-headers/', 'amenities/', 'category-icons/', 'media-library/',
        'category-pages/', 'photo-references/', 'instruction-steps/', 'properties/', 'videos/',
    ];

    // Other tables/columns that may point at the same file. Fill in once confirmed.
    private const OTHER_REFERENCES = [
        // ['task_media', 'url'],
    ];

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);
        $stats = ['rows' => 0, 'files_kept' => 0, 'protected' => 0, 'errors' => 0];

        $this->info(($dry ? '[DRY RUN] ' : '') . "Pruning cleaner photos older than {$days} days ({$cutoff})");

        foreach ([RoomPhoto::class, ChecklistItemPhoto::class] as $model) {
            $model::where('created_at', '<', $cutoff)->chunkById(200, function ($photos) use ($model, $cutoff, $dry, &$stats) {
                foreach ($photos as $photo) {
                    try {
                        $path = $photo->path;

                        if (filled($path) && $this->isProtected($path)) {
                            $stats['protected']++;
                            Log::warning('photos:prune-old SKIPPED protected path', ['model' => $model, 'id' => $photo->id, 'path' => $path]);
                            continue;
                        }

                        $keep = filled($path) && $this->stillReferenced($path, $cutoff, $model, $photo->id);
                        if ($keep) {
                            $stats['files_kept']++;
                        }

                        $this->line("{$model} #{$photo->id} {$path}" . ($keep ? ' (file kept, still referenced)' : ''));
                        Log::info('photos:prune-old delete', ['model' => $model, 'id' => $photo->id, 'path' => $path, 'dry' => $dry, 'file_kept' => $keep]);

                        if (! $dry) {
                            if (filled($path) && ! $keep) {
                                PersistentPhotoStorage::delete($path);
                            }
                            $photo->delete();
                        }
                        $stats['rows']++;
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::error('photos:prune-old failed', ['id' => $photo->id, 'error' => $e->getMessage()]);
                    }
                }
            });
        }

        $this->info(json_encode($stats));
        return self::SUCCESS;
    }

    private function isProtected(string $path): bool
    {
        $p = ltrim(preg_replace('#^/?storage/#', '', str_replace('\\', '/', trim($path))), '/');
        if (! str_starts_with($p, 'room_photos/') && ! str_starts_with($p, 'checklist-photos/')) {
            return true; // allowlist: only cleaner photo folders may ever be deleted
        }
        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if (str_starts_with($p, $prefix)) {
                return true;
            }
        }
        return false;
    }

    // True if a newer row, or a row in another table, still points at this file.
    private function stillReferenced(string $path, $cutoff, string $selfModel, $selfId): bool
    {
        foreach ([RoomPhoto::class, ChecklistItemPhoto::class] as $m) {
            $q = $m::where('path', $path);
            if ($m === $selfModel) {
                $q->where('id', '!=', $selfId);
            }
            if ($q->where('created_at', '>=', $cutoff)->exists()) {
                return true;
            }
        }
        foreach (self::OTHER_REFERENCES as [$table, $col]) {
            if (DB::table($table)->where($col, $path)->exists()) {
                return true;
            }
        }
        return false;
    }
}
