<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Pms\PriceLabsSync;
use Illuminate\Console\Command;

class PriceLabsSyncCommand extends Command
{
    protected $signature = 'pricelabs:sync {--property= : Only this property id} {--force : Ignore the last_refreshed check}';

    protected $description = 'Pull PriceLabs prices and queue only changed dates for Channex';

    public function handle(PriceLabsSync $sync): int
    {
        $props = Property::query()
            ->whereNotNull('pricelabs_listing_id')
            ->when($this->option('property'), fn ($q, $id) => $q->where('id', (int) $id))
            ->get();

        foreach ($props as $p) {
            $r = $sync->sync($p, (bool) $this->option('force'));
            $this->line("#{$p->id} {$p->name}: {$r['status']} - {$r['message']}");
            sleep(1);
        }

        return self::SUCCESS;
    }
}
