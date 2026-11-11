<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PurgeDeclinedIds extends Command
{
    protected $signature = 'ids:purge-declined {--dry-run : List what would be deleted}';

    protected $description = 'Delete archived/declined guest ID images and IDs of closed bookings after the retention period.';

    public function handle(): int
    {
        $days = filter_var(Setting::getValue('lease_application_retention_days', 90), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]]);
        if ($days === false) {
            $this->error('Retention days must be an integer from 1 to 3650.');

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);
        $disk = Storage::disk('local');
        $count = 0;

        foreach ($disk->allFiles('photo-ids-archive') as $file) {
            $when = null;
            if (preg_match('/(\d{8}-\d{6})-(?:front|back)-/', basename($file), $m)) {
                $when = Carbon::createFromFormat('Ymd-His', $m[1]);
            }
            $when ??= Carbon::createFromTimestamp($disk->lastModified($file));
            if ($when->gt($cutoff)) {
                continue;
            }
            $this->line(($dry ? 'Would delete ' : 'Deleting ').$file);
            if (! $dry) {
                $disk->delete($file);
            }
            $count++;
        }

        Booking::query()
            ->whereIn('status', ['cancelled', 'canceled', 'declined', 'expired'])
            ->where('updated_at', '<=', $cutoff)
            ->where(fn ($q) => $q->whereNotNull('photo_id_path')->orWhereNotNull('photo_id_back_path'))
            ->chunkById(100, function ($bookings) use ($dry, $disk, &$count): void {
                foreach ($bookings as $booking) {
                    $this->line(($dry ? 'Would clear ' : 'Clearing ')."IDs for booking {$booking->booking_id}");
                    if (! $dry) {
                        foreach (['photo_id_path', 'photo_id_back_path'] as $field) {
                            if ($booking->{$field} && $disk->exists($booking->{$field})) {
                                $disk->delete($booking->{$field});
                            }
                        }
                        $booking->forceFill(['photo_id_path' => null, 'photo_id_back_path' => null])->saveQuietly();
                    }
                    $count++;
                }
            });

        $this->info(($dry ? 'Would purge ' : 'Purged ')."{$count} items.");

        return self::SUCCESS;
    }
}
