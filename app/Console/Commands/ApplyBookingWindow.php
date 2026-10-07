<?php

namespace App\Console\Commands;

use App\Models\Property;
use App\Services\Pms\AvailabilityLedger;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ApplyBookingWindow extends Command
{
    protected $signature = 'availability:apply-window {--property= : Only this property id}';

    protected $description = 'Close dates beyond each property\'s booking window and reopen window-closed dates that roll inside it';

    public function handle(AvailabilityLedger $ledger): int
    {
        $tz = config('app.display_timezone') ?: config('app.timezone');
        $today = Carbon::now($tz)->startOfDay();
        $last = $today->copy()->addDays(499);

        $props = Property::query()
            ->when($this->option('property'), fn ($q, $id) => $q->where('id', (int) $id))
            ->where(function ($q) {
                $q->whereNotNull('close_ahead_days')
                    ->orWhereHas('availabilities', fn ($a) => $a->where('source', 'window'));
            })
            ->get();

        foreach ($props as $p) {
            $n = $p->close_ahead_days;
            $cutoff = $n === null ? null : $today->copy()->addDays((int) $n);

            $rows = $p->availabilities()
                ->whereDate('date', '>=', $today->toDateString())
                ->whereDate('date', '<=', $last->toDateString())
                ->get()
                ->keyBy(fn ($r) => $r->date->format('Y-m-d'));

            $toClose = [];
            $toOpen = [];

            for ($d = $today->copy(); $d->lte($last); $d->addDay()) {
                $key = $d->format('Y-m-d');
                $row = $rows->get($key);
                $beyond = $cutoff && $d->gt($cutoff);

                if ($beyond) {
                    if (! $row || ($row->is_available && $row->source !== 'manual')) {
                        $toClose[] = $key;
                    }
                } elseif ($row && $row->source === 'window' && $row->status === 'blocked') {
                    $toOpen[] = $key;
                }
            }

            $ledger->setDates($p, $toClose, ['status' => 'blocked'], 'window');
            $ledger->setDates($p, $toOpen, ['status' => 'available'], 'window');

            if ($toOpen) {
                // Nights held by a booking must close again.
                $ledger->syncNights($p->id, $today->toDateString(), $last->copy()->addDay()->toDateString());
            }

            $this->line("#{$p->id} {$p->name}: closed ".count($toClose).', reopened '.count($toOpen).'.');
        }

        return self::SUCCESS;
    }
}
