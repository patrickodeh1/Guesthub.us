<?php

namespace App\Console\Commands;

use App\Models\LeaseOffer;
use App\Services\Pms\AvailabilityLedger;
use Illuminate\Console\Command;

class ExpireLeaseOffers extends Command
{
    protected $signature = 'lease-offers:expire';
    protected $description = 'Expire lease offers that passed their acceptance deadline and release held dates.';

    public function handle(): int
    {
        $count = 0;
        LeaseOffer::query()->whereIn('status', [LeaseOffer::STATUS_SENT, LeaseOffer::STATUS_SIGNED])
            ->whereNotNull('accept_by')->where('accept_by', '<=', now())
            ->with('booking')->chunkById(100, function ($offers) use (&$count) {
                foreach ($offers as $offer) {
                    \Illuminate\Support\Facades\DB::transaction(function () use ($offer, &$count) {
                        $locked = LeaseOffer::query()->whereKey($offer->id)->lockForUpdate()->first();
                        if (! $locked || ! in_array($locked->status, [LeaseOffer::STATUS_SENT, LeaseOffer::STATUS_SIGNED], true) || $locked->accept_by?->isFuture()) return;
                        $locked->update(['status' => LeaseOffer::STATUS_EXPIRED, 'expired_at' => now()]);
                        if ($locked->booking) $locked->booking->update(['status' => 'expired', 'cancelled_at' => now(), 'hold_expires_at' => null]);
                        $count++;
                    });
                    if ($offer->booking) app(AvailabilityLedger::class)->syncBooking($offer->booking->fresh());
                }
            });
        $this->info("Expired {$count} lease offers.");
        return self::SUCCESS;
    }
}
