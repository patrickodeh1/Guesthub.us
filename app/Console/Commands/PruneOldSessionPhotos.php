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
    protected $signature = 'photos:prune-old
        {--issue-days=60 : Days to keep photos attached to issue items}
        {--verification-days=30 : Days to keep verification photos}
        {--days=15 : Days to keep all other cleaner photos}
        {--dry-run : List only}';
    protected $description = 'Delete expired cleaner photo files and mark their rows as purged (issue 60d, verification 30d, other 15d)';

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
        $issueDays = max(1, (int) $this->option('issue-days'));
        $verifyDays = max(1, (int) $this->option('verification-days'));
        $otherDays = max(1, (int) $this->option('days'));
        $dry = (bool) $this->option('dry-run');
        $earliest = now()->subDays(min($issueDays, $verifyDays, $otherDays));
        $stats = ['purged' => 0, 'files_kept' => 0, 'protected' => 0, 'errors' => 0];

        $this->info(($dry ? '[DRY RUN] ' : '') . "Retention: issue {$issueDays}d, verification {$verifyDays}d, other {$otherDays}d");

        foreach ([RoomPhoto::class, ChecklistItemPhoto::class] as $model) {
            $query = $model::whereNull('purged_at')->where('retained', false)->where('created_at', '<', $earliest);
            if ($model === ChecklistItemPhoto::class) {
                $query->with('checklistItem.task');
            }

            $query->chunkById(200, function ($photos) use ($model, $issueDays, $verifyDays, $otherDays, $dry, &$stats) {
                foreach ($photos as $photo) {
                    try {
                        [$class, $days] = $this->classify($photo, $issueDays, $verifyDays, $otherDays);
                        if ($photo->created_at->gte(now()->subDays($days))) {
                            continue; // not expired for its category yet
                        }

                        $path = $photo->path;

                        if (filled($path) && $this->isProtected($path)) {
                            $stats['protected']++;
                            Log::warning('photos:prune-old SKIPPED protected path', ['model' => $model, 'id' => $photo->id, 'path' => $path]);
                            continue;
                        }

                        $keep = filled($path) && $this->stillReferenced($path, $model, $photo->id);
                        if ($keep) {
                            $stats['files_kept']++;
                        }

                        $this->line("[{$class}] {$model} #{$photo->id} {$path}" . ($keep ? ' (file kept, still referenced)' : ''));
                        Log::info('photos:prune-old purge', ['model' => $model, 'id' => $photo->id, 'class' => $class, 'path' => $path, 'dry' => $dry, 'file_kept' => $keep]);

                        if (! $dry) {
                            if (filled($path) && ! $keep) {
                                PersistentPhotoStorage::delete($path);
                            }
                            $photo->forceFill(['purged_at' => now()])->save();
                        }
                        $stats['purged']++;
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

    /** @return array{0:string,1:int} [class label, retention days] */
    private function classify($photo, int $issueDays, int $verifyDays, int $otherDays): array
    {
        if ($photo instanceof ChecklistItemPhoto && $photo->checklistItem) {
            $item = $photo->checklistItem;

            // Any photo on a non-verification item makes it an issue (see isChecklistIssue()).
            if (! $item->isVerificationItem() || $item->hasComment()) {
                return ['issue', $issueDays];
            }

            return ['verification', $verifyDays];
        }

        return ['other', $otherDays];
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

    // True if another not-yet-purged row, or a row in another table, still points at this file.
    private function stillReferenced(string $path, string $selfModel, $selfId): bool
    {
        foreach ([RoomPhoto::class, ChecklistItemPhoto::class] as $m) {
            $q = $m::where('path', $path)->whereNull('purged_at');
            if ($m === $selfModel) {
                $q->where('id', '!=', $selfId);
            }
            if ($q->exists()) {
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
