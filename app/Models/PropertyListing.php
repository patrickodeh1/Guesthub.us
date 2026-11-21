<?php

namespace App\Models;

use App\Jobs\PublishListingToChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyListing extends Model
{
    /**
     * Keep already-published channel listings in sync when their canonical
     * GuestHub content changes. New/unpublished listings are never pushed
     * automatically; the host must explicitly publish them first.
     */
    protected static function booted(): void
    {
        static::updated(function (self $listing): void {
            $contentFields = [
                'property_id', 'public_title', 'public_description', 'max_guests',
                'bedrooms', 'property_type', 'room_type', 'base_rate_cents', 'cleaning_fee_cents',
                'cancellation_policy', 'house_rules', 'listing_type', 'rooms_sync_enabled', 'amenities_sync_enabled',
            ];

            if (blank($listing->published_at) || ! $listing->wasChanged($contentFields)) {
                return;
            }

            self::queueChannelContentSync($listing->id);
        });
    }

    public static function queueChannelContentSync(int $listingId): void
    {
        $listing = self::query()->with('channelLinks')->find($listingId);
        if (! $listing || blank($listing->published_at)) {
            return;
        }

        foreach ($listing->channelLinks->whereIn('state', ['published', 'live', 'partial']) as $link) {
            PublishListingToChannel::dispatch($listing->id, (string) $link->channel)->afterCommit();
        }
    }

    protected $fillable = [
        'property_id',
        'listing_slug',
        'public_title',
        'public_description',
        'max_guests',
        'bedrooms',
        'property_type',
        'room_type',
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
        'listing_type',
        'lease_auto_route_29_nights',
        'lease_application_fee_cents',
        'screening_provider_url',
        'lease_allow_passport',
        'lease_fee_refund_policy',
        'published_at',
        'meta_title',
        'meta_description',
        'repull_listing_id',
        'rooms_sync_enabled',
        'amenities_sync_enabled',
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
            'lease_auto_route_29_nights' => 'boolean',
            'lease_application_fee_cents' => 'integer',
            'published_at' => 'datetime',
            'rooms_sync_enabled' => 'boolean',
            'amenities_sync_enabled' => 'boolean',
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

    public function listingRooms(): HasMany
    {
        return $this->hasMany(ListingRoom::class, 'listing_id')->orderBy('room_number')->orderBy('id');
    }

    public function channelLinks(): HasMany
    {
        return $this->hasMany(ListingChannelLink::class, 'listing_id');
    }

    public function leaseApplications(): HasMany
    {
        return $this->hasMany(LeaseApplication::class, 'listing_id');
    }
}
