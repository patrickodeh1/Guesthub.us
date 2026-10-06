<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per property+date: Guesthub's ledger of availability, rates and
 * restrictions, pushed to Channex (which pushes to Airbnb and other OTAs).
 * Change rows through App\Services\Pms\AvailabilityLedger so the outbox
 * is kept in step. Nullable rate/restriction columns mean "not set" and are
 * never sent. Rates are only sent for properties with rate_source = guesthub.
 */
class PropertyAvailability extends Model
{
    protected $fillable = [
        'property_id', 'date', 'is_available', 'status', 'source',
        'rate', 'min_stay_arrival', 'min_stay_through', 'max_stay',
        'stop_sell', 'closed_to_arrival', 'closed_to_departure',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_available' => 'boolean',
            'rate' => 'decimal:2',
            'stop_sell' => 'boolean',
            'closed_to_arrival' => 'boolean',
            'closed_to_departure' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
