<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyListing extends Model
{
    protected $fillable = [
        'property_id',
        'listing_slug',
        'public_title',
        'public_description',
        'max_guests',
        'bedrooms',
        'base_rate_cents',
        'cleaning_fee_cents',
        'tax_percent',
        'booking_mode',
        'advance_notice_hours',
        'same_day_cutoff_time',
        'min_nights',
        'cancellation_policy',
        'cancellation_policy_text',
        'house_rules',
        'lease_enabled',
        'lease_application_fee_cents',
        'screening_provider_url',
        'published_at',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'max_guests' => 'integer',
            'bedrooms' => 'integer',
            'base_rate_cents' => 'integer',
            'cleaning_fee_cents' => 'integer',
            'tax_percent' => 'decimal:2',
            'advance_notice_hours' => 'integer',
            'min_nights' => 'integer',
            'lease_enabled' => 'boolean',
            'lease_application_fee_cents' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ListingPhoto::class, 'listing_id')->orderBy('sort_order')->orderBy('id');
    }

    public function leaseApplications(): HasMany
    {
        return $this->hasMany(LeaseApplication::class, 'listing_id');
    }
}
