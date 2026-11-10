<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Pms\PriceLabsSync;
use Illuminate\Console\Command;

class WheelhouseSyncCommand extends Command
{
    protected $signature = 'wheelhouse:sync {--property= : Only this property id} {--force : Force a fresh pull}';

    protected $description = 'Pull Wheelhouse recommendations into the availability ledger';

    public function handle(PriceLabsSync $sync): int
    {
        $properties = Property::query()
            ->where('pricing_provider', 'wheelhouse')
            ->whereNotNull('wheelhouse_listing_id')
            ->when($this->option('property'), fn ($query, $id) => $query->whereKey((int) $id))
            ->get();

        foreach ($properties as $property) {
            $result = $sync->sync($property, (bool) $this->option('force'));
            $this->line("#{$property->id} {$property->name}: {$result['status']} - {$result['message']}");
            sleep(1);
        }

        return self::SUCCESS;
    }
}
