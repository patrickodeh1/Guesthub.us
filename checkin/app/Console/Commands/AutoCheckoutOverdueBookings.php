<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class AutoCheckoutOverdueBookings extends Command
{
    /**
     * Grace period, in minutes, given to a guest after their checkout time
     * before their booking is automatically marked checked_out. Client-
     * requested value for task 23; change here if that value ever changes.
     */
    protected $signature = 'bookings:auto-checkout {--grace=30 : Grace period in minutes after checkout time}';

    protected $description = 'Automatically checks out bookings whose guests did not confirm checkout, after a grace period past their checkout time';

    public function handle(): int
    {
        $grace = (int) $this->option('grace');
        $count = Booking::autoCheckoutOverdue($grace);

        $this->info("Auto-checked-out {$count} booking(s) past their {$grace}-minute checkout grace period.");

        return self::SUCCESS;
    }
}
