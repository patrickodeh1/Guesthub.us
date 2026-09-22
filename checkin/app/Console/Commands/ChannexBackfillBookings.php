<?php

namespace App\Console\Commands;

use App\Services\Pms\BookingImportService;
use App\Services\Pms\PmsProviderInterface;
use Illuminate\Console\Command;

/**
 * One-time (per gap) backfill: pulls bookings that already existed on the
 * OTA/channel manager before Guesthub's regular sync (revision feed) ever
 * saw them, and imports them the same way SyncPmsBookings does.
 *
 * This is intentionally separate from `pms:sync` and must NOT be scheduled
 * -- it exists only because Channex's revision feed permanently drops a
 * booking once acknowledged, so anything acknowledged (or existing) before
 * polling started is unrecoverable through the normal sync path. Channex's
 * own certification guidance is explicit that GET /bookings should only be
 * used for this kind of one-off historical pull, never for ongoing polling
 * -- see PmsProviderInterface::getAllBookings() and
 * ChannexProvider::getAllBookings().
 *
 * Run manually, e.g.:
 *   php artisan channex:backfill-bookings
 *   php artisan channex:backfill-bookings --from=2026-09-01 --to=2026-10-31
 */
class ChannexBackfillBookings extends Command
{
    protected $signature = 'channex:backfill-bookings
        {--from= : Arrival date range start (Y-m-d). Omit to leave unbounded.}
        {--to= : Arrival date range end (Y-m-d). Omit to leave unbounded.}
        {--dry-run : List what would be imported without writing anything.}';

    protected $description = 'One-time backfill of existing/future bookings via Channex Bookings Collection (GET /bookings) -- for gaps the revision feed can never recover. Do not schedule.';

    public function handle(PmsProviderInterface $provider, BookingImportService $importer): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $dryRun = (bool) $this->option('dry-run');

        $dateRange = null;
        if ($from || $to) {
            $dateRange = array_filter([
                'arrival_date_from' => $from,
                'arrival_date_to' => $to,
            ]);
        }

        $this->info('Fetching bookings from Channex (Bookings Collection, backfill-only endpoint)...');

        $bookings = $provider->getAllBookings($dateRange);

        $this->info(sprintf('Fetched %d booking(s) from Channex.', count($bookings)));

        if ($dryRun) {
            $this->table(
                ['External Booking ID', 'Property ID', 'Guest', 'Check-in', 'Check-out', 'Status'],
                array_map(fn ($b) => [
                    $b->externalBookingId,
                    $b->externalPropertyId,
                    $b->guestName ?? '(none)',
                    $b->checkInDate,
                    $b->checkOutDate,
                    $b->status ?? '(none)',
                ], $bookings)
            );
            $this->comment('Dry run -- nothing was imported.');
            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($bookings as $pmsBooking) {
            // NOTE: deliberately no acknowledgeBooking() call here.
            // Acknowledgement only applies to the revision feed
            // (revisionId is always null on backfilled PmsBooking objects
            // -- see ChannexProvider::normalizeBooking()). Acking a
            // Bookings Collection record would be meaningless and could
            // 404 against the real API.
            $result = $importer->import($pmsBooking);

            if ($result) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        $this->info("Backfill complete: {$imported} imported/updated, {$skipped} skipped (property not mapped or missing dates).");

        return self::SUCCESS;
    }
}
