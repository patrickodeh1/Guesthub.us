<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\CleaningFlowService;
use Illuminate\Console\Command;

class SyncCheckoutCleaningSessions extends Command
{
    protected $signature = 'cleaning:sync-checkout-sessions';

    protected $description = 'Creates/updates one unassigned cleaning session per upcoming checkout (safety net for PMS imports and backfill)';

    public function handle(): int
    {
        $n = 0;
        Booking::query()->notArchived()->whereNull('cancelled_at')
            ->whereDate('check_out_date', '>=', now(config('app.display_timezone'))->toDateString())
            ->with('property')->get()
            ->each(function ($b) use (&$n) { CleaningFlowService::syncForBooking($b); $n++; });
        $this->info("Checked {$n} booking(s).");
        return self::SUCCESS;
    }
}
