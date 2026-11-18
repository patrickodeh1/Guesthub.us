<?php

namespace App\\Jobs;

use App\\Models\\Property;
use App\\Services\\Pms\\PriceLabs\\PriceLabsIapiSync;
use Illuminate\\Bus\\Queueable;
use Illuminate\\Contracts\\Queue\\ShouldQueue;
use Illuminate\\Foundation\\Bus\\Dispatchable;
use Illuminate\\Queue\\InteractsWithQueue;
use Illuminate\\Queue\\SerializesModels;
use Illuminate\\Support\\Facades\\Log;
use Throwable;

class PushPriceLabsCalendarToIapi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $propertyId,
        public string $startDate,
        public string $endDate,
    ) {}

    public function handle(PriceLabsIapiSync $sync): void
    {
        $property = Property::query()
            ->whereKey($this->propertyId)
            ->where('pricing_provider', 'pricelabs')
            ->where('pricelabs_mode', 'pms')
            ->whereNotNull('pricelabs_iapi_listing_id')
            ->first();

        if (! $property) {
            return;
        }

        $sync->pushCalendar($property, $this->startDate, $this->endDate);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Queued PriceLabs calendar sync failed.', [
            'property_id' => $this->propertyId,
            'exception' => get_class($exception),
        ]);
    }
}
