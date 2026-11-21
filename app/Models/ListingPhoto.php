<?php

namespace App\Models;

use App\Jobs\DeleteRepullPhoto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingPhoto extends Model
{
    protected static function booted(): void
    {
        $syncListing = static function (self $photo): void {
            $listingId = (int) $photo->listing_id;
            if ($listingId > 0) {
                PropertyListing::queueChannelContentSync($listingId);
            }
        };

        static::created($syncListing);
        static::updating(function (self $photo): void {
            if ($photo->isDirty('path')) {
                if (filled($photo->repull_photo_listing_id) && filled($photo->repull_photo_path)) {
                    DeleteRepullPhoto::dispatch((string) $photo->repull_photo_listing_id, (string) $photo->repull_photo_path)->afterCommit();
                }
                $photo->repull_photo_listing_id = null;
                $photo->repull_photo_path = null;
                $photo->repull_photo_url = null;
                $photo->repull_uploaded_at = null;
                $photo->source_channel = null;
                $photo->source_listing_id = null;
                $photo->source_photo_id = null;
                $photo->booking_listing_id = null;
                $photo->booking_uploaded_at = null;
            }
        });
        static::updated(function (self $photo) use ($syncListing): void {
            if ($photo->wasChanged(['path', 'alt', 'sort_order'])) {
                $syncListing($photo);
            }
        });
        static::deleting(function (self $photo): void {
            if (filled($photo->repull_photo_listing_id) && filled($photo->repull_photo_path)) {
                DeleteRepullPhoto::dispatch((string) $photo->repull_photo_listing_id, (string) $photo->repull_photo_path)->afterCommit();
            }
        });

        static::deleted($syncListing);
    }

    protected $fillable = [
        'listing_id', 'path', 'thumbnail_path', 'alt', 'sort_order', 'width', 'height',
        'source_channel', 'source_listing_id', 'source_photo_id',
        'booking_listing_id', 'booking_uploaded_at',
        'repull_photo_listing_id', 'repull_photo_path', 'repull_photo_url', 'repull_uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer', 'width' => 'integer', 'height' => 'integer',
            'booking_uploaded_at' => 'datetime', 'repull_uploaded_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class, 'listing_id');
    }
}
