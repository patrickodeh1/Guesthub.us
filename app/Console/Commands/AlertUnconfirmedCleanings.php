<?php

namespace App\Console\Commands;

use App\Services\CleaningFlowService;
use Illuminate\Console\Command;

class AlertUnconfirmedCleanings extends Command
{
    protected $signature = 'cleaning:alert-unconfirmed {--hours=2 : Hours of silence before alerting}';

    protected $description = 'Alerts the admins when a cleaner has not replied YES/NO to an assignment text';

    public function handle(): int
    {
        $n = CleaningFlowService::alertUnconfirmed((int) $this->option('hours'));
        $this->info("Sent {$n} unconfirmed-cleaning alert(s).");

        return self::SUCCESS;
    }
}
