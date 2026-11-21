<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingRoom extends Model
{
    protected static function booted(): void
    {
        $sync = static function (self $room): void {
            $listing = PropertyListing::query()->find($room->listing_id);
            if ($listing && $listing->rooms_sync_enabled) {
                PropertyListing::queueChannelContentSync((int) $listing->id);
            }
        };

        static::created($sync);
        static::updated(function (self $room) use ($sync): void {
            if ($room->wasChanged(['room_type', 'room_number', 'room_name', 'bed_type', 'bed_quantity', 'is_private'])) {
                $sync($room);
            }
        });
        static::deleted($sync);
    }

    protected $fillable = [
        'listing_id', 'room_type', 'room_number', 'room_name',
        'bed_type', 'bed_quantity', 'is_private', 'booking_room_id', 'booking_rate_id',
    ];

    protected function casts(): array
    {
        return [
            'room_number' => 'integer',
            'bed_quantity' => 'integer',
            'is_private' => 'boolean',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class, 'listing_id');
    }
}
