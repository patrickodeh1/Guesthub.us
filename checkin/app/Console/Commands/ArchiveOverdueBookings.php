<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class ArchiveOverdueBookings extends Command
{
    /**
     * Was previously triggered as a side effect of loading the admin guest
     * list or dashboard (Booking::archiveOverdue() called directly in those
     * controllers) — the same page-load-polling pattern task 23 removed for
     * auto-checkout. Moved to a real scheduled command for the same reasons:
     * predictable cadence instead of "whenever an admin happens to load a
     * page", and no full-table scan + per-row time comparison on every
     * admin page view.
     */
    protected $signature = 'bookings:archive-overdue';

    protected $description = 'Automatically archives bookings that are past their checkout time';

    public function handle(): int
    {
        $count = Booking::archiveOverdue();

        $this->info("Archived {$count} overdue booking(s).");

        return self::SUCCESS;
    }
}
