<?php

namespace App\Console\Commands;

use App\Models\Property;
use Illuminate\Console\Command;

class ReportSharedKeyUsageCommand extends Command
{
    protected $signature = 'integrations:report-shared-key-usage';

    protected $description = 'List PriceLabs properties whose owners have no individual API key';

    public function handle(): int
    {
        $properties = Property::query()
            ->with('owner:id,name')
            ->where('pricing_provider', 'pricelabs')
            ->whereDoesntHave('owner', fn ($query) => $query->whereNotNull('pricelabs_api_key')->where('pricelabs_api_key', '!=', ''))
            ->orderBy('id')
            ->get(['id', 'name', 'owner_id', 'pricelabs_listing_id']);

        if ($properties->isEmpty()) {
            $this->info('No PriceLabs properties are missing a host API key.');

            return self::SUCCESS;
        }

        $this->warn('These properties need their host to add a PriceLabs API key. No data was changed.');
        $this->table(
            ['Property ID', 'Property', 'PriceLabs Listing ID', 'Owner ID', 'Owner'],
            $properties->map(fn (Property $property) => [
                $property->id,
                $property->name,
                $property->pricelabs_listing_id,
                $property->owner_id,
                $property->owner?->name ?? 'Unassigned',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
