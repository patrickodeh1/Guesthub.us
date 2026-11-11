<?php

namespace App\Console\Commands;

use App\Models\LeaseApplication;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgeLeaseApplications extends Command
{
    protected $signature = 'lease-applications:purge {--dry-run : List eligible applications without deleting them}';

    protected $description = 'Delete declined lease applications after the configured retention period and expired drafts.';

    public function handle(): int
    {
        $retentionDays = filter_var(
            Setting::getValue('lease_application_retention_days', 90),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 3650]],
        );
        if ($retentionDays === false) {
            $this->error('lease_application_retention_days must be an integer from 1 to 3650.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $declinedBefore = now()->subDays($retentionDays);
        $draftsBefore = now()->subDay();
        $purged = 0;
        $errors = 0;

        $query = LeaseApplication::query()
            ->where(function ($applications) use ($declinedBefore, $draftsBefore) {
                $applications->where(fn ($query) => $query
                    ->where('status', 'declined')
                    ->whereNotNull('declined_at')
                    ->where('declined_at', '<=', $declinedBefore))
                    ->orWhere(fn ($query) => $query
                        ->where('status', 'draft')
                        ->where('created_at', '<=', $draftsBefore));
            });

        $query->chunkById(100, function ($applications) use ($dryRun, &$purged, &$errors): void {
            foreach ($applications as $application) {
                if ($dryRun) {
                    $this->line("Would purge lease application #{$application->id}");
                    $purged++;

                    continue;
                }

                $failed = false;
                foreach (array_filter([$application->id_scan_path, $application->id_back_scan_path]) as $path) {
                    if (Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) {
                        $failed = true;
                    }
                }
                if ($failed) {
                    $errors++;
                    $this->error("Could not delete the private ID image for application #{$application->id}.");
                    Log::error('Lease application ID image purge failed.', ['application_id' => $application->id]);

                    continue;
                }

                DB::transaction(fn () => $application->delete());
                $purged++;
            }
        });

        $this->info(($dryRun ? 'Would purge ' : 'Purged ').$purged." lease applications; {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
