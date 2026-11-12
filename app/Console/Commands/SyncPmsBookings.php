<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Pms\BookingImportService;
use App\Services\Pms\PmsProviderInterface;
use Illuminate\Console\Command;

class SyncPmsBookings extends Command
{
    protected $signature = 'pms:sync';

    protected $description = 'Polls the active PMS provider (Channex/NextPax) for new or changed bookings and imports them into Guesthub';

    /**
     * Settings key storing the timestamp of the last successful poll, used
     * as the "since" cursor for the next run so we only fetch what's
     * changed rather than the whole feed every time.
     */
    private const LAST_SYNC_KEY = 'pms_last_sync_at';

    public function handle(PmsProviderInterface $provider, BookingImportService $importer): int
    {
        $since = Setting::getValue(self::LAST_SYNC_KEY);
        $sinceDate = $since ? \Carbon\Carbon::parse($since) : null;

        $startedAt = now();
        try {
            $bookings = $provider->getBookings($sinceDate);
        } catch (\Throwable $e) {
            // Network blips must not fail the scheduler. Nothing was acknowledged and the
            // cursor is untouched, so the next poll retries the same window.
            \Illuminate\Support\Facades\Log::warning('pms:sync could not fetch bookings', ['error' => $e->getMessage()]);
            $this->warn('PMS sync skipped: provider unreachable.');

            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($bookings as $pmsBooking) {
            try {
                $result = $importer->import($pmsBooking);

                if ($result) {
                    $imported++;
                    // Channex acknowledges Booking Revisions, not Bookings —
                    // must use revisionId here, not externalBookingId, or the
                    // ack call 404s against the real API.
                    $provider->acknowledgeBooking($pmsBooking->revisionId ?? $pmsBooking->externalBookingId);
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                report($e);
            }
        }

        // Only move the cursor when everything went through, so failed bookings are retried.
        if ($failed === 0) {
            Setting::putValue(self::LAST_SYNC_KEY, $startedAt->toIso8601String());
        }

        $this->info("PMS sync complete: {$imported} imported/updated, {$skipped} skipped (property not mapped), {$failed} failed.");

        return self::SUCCESS;
    }
}
