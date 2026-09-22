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
        $bookings = $provider->getBookings($sinceDate);

        $imported = 0;
        $skipped = 0;

        foreach ($bookings as $pmsBooking) {
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
        }

        Setting::putValue(self::LAST_SYNC_KEY, $startedAt->toIso8601String());

        $this->info("PMS sync complete: {$imported} imported/updated, {$skipped} skipped (property not mapped).");

        return self::SUCCESS;
    }
}
