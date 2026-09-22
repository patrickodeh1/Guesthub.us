<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per property+date: Guesthub's own ledger of availability, pushed
 * outward to Channex (which then pushes to Airbnb and other OTAs). Rates
 * are deliberately NOT tracked here -- PriceLabs pushes rates directly
 * into Channex, so Guesthub has no rate-management role. This table exists
 * solely to bootstrap/reconcile availability from Airbnb's iCal export,
 * since Airbnb locks manual calendar edits once Channex is the mapped
 * channel manager.
 */
class PropertyAvailability extends Model
{
    protected $fillable = [
        'property_id', 'date', 'is_available', 'source',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_available' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
