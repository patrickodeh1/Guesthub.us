<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RoomPhoto;
use App\Models\ChecklistItemPhoto;
use App\Services\PersistentPhotoStorage;
use Illuminate\Support\Facades\Storage;

class PruneOldSessionPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:prune-old {--days=14 : Number of days to keep photos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune room and checklist photos older than the specified number of days to free up server space';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);
        $this->info("Pruning session photos older than {$days} days ({$cutoffDate})...");

        $prunedCount = 0;
        $freedSpace = 0;

        // Prune RoomPhotos
        $roomPhotos = RoomPhoto::where('created_at', '<', $cutoffDate)->get();
        foreach ($roomPhotos as $photo) {
            $path = $photo->path;
            if ($this->deleteFile($path)) {
                $freedSpace++;
            }
            $photo->delete();
            $prunedCount++;
        }

        // Prune ChecklistItemPhotos
        $checklistPhotos = ChecklistItemPhoto::where('created_at', '<', $cutoffDate)->get();
        foreach ($checklistPhotos as $photo) {
            $path = $photo->path;
            if ($this->deleteFile($path)) {
                $freedSpace++;
            }
            $photo->delete();
            $prunedCount++;
        }

        $this->info("Prune complete. Deleted {$prunedCount} database records and freed associated files.");
    }

    private function deleteFile($path)
    {
        $deleted = false;
        
        // Remove from physical disk if exists
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            $deleted = true;
        }
        
        // Remove from Persistent DB Blob table if exists
        if (class_exists(PersistentPhotoStorage::class)) {
            PersistentPhotoStorage::delete($path);
            $deleted = true;
        }
        
        return $deleted;
    }
}
