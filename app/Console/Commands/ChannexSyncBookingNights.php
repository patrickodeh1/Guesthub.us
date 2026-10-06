<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Models\PropertyAvailability;
use App\Services\Pms\AvailabilityLedger;
use Illuminate\Console\Command;

class ChannexSyncBookingNights extends Command
{
    protected $signature = 'channex:sync-booking-nights {--property= : Only this property id} {--days=500 : How many days ahead to recompute}';

    protected $description = 'Recompute booking-held nights (booked/available) for existing bookings from today onward';

    public function handle(AvailabilityLedger $ledger): int
    {
        $from = today()->toDateString();
        $to = today()->addDays((int) $this->option('days'))->toDateString();

        $properties = Property::query()
            ->when($this->option('property'), fn ($q, $id) => $q->where('id', (int) $id))
            ->get(['id', 'name']);

        foreach ($properties as $p) {
            $before = PropertyAvailability::where('property_id', $p->id)->where('source', 'booking')->count();
            $ledger->syncNights($p->id, $from, $to);
            $after = PropertyAvailability::where('property_id', $p->id)->where('source', 'booking')->count();

            $this->line("#{$p->id} {$p->name}: booking-held nights {$before} -> {$after}");
        }

        return self::SUCCESS;
    }
}
