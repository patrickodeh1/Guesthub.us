<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\Pms\PriceLabs\PriceLabsIapiSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PushPriceLabsBookingToIapi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $propertyId,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public array $cancelledBlockDates = [],
    ) {}

    public function handle(PriceLabsIapiSync $sync): void
    {
        $property = Property::query()
            ->whereKey($this->propertyId)
            ->where('pricing_provider', 'pricelabs')
            ->where('pricelabs_mode', 'pms')
            ->first();

        if (! $property || ! $property->pricelabs_iapi_listing_id) {
            return;
        }

        $sync->pushReservations($property);
        if ($this->cancelledBlockDates !== []) {
            $sync->cancelBlockedReservations($property, $this->cancelledBlockDates);
        }

        if ($this->startDate && $this->endDate) {
            $sync->pushCalendar($property, $this->startDate, $this->endDate);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Queued PriceLabs booking sync failed.', [
            'property_id' => $this->propertyId,
            'exception' => get_class($exception),
        ]);
    }
}
