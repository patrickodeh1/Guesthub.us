<?php

namespace App\Console\Commands;

use App\Services\Pms\ChannexOutboxSender;
use Illuminate\Console\Command;

class ChannexPushOutbox extends Command
{
    protected $signature = 'channex:push-outbox {--property= : Only this property id}';

    protected $description = 'Send pending availability/rate/restriction changes to Channex';

    public function handle(ChannexOutboxSender $sender): int
    {
        $stats = $sender->flush($this->option('property') ? (int) $this->option('property') : null);

        $this->info("Calls: {$stats['calls']}, dates sent: {$stats['sent']}, dates failed: {$stats['failed']}");

        return self::SUCCESS;
    }
}
