<?php

namespace App\Models;

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
        static::updated(function (self $photo) use ($syncListing): void {
            if ($photo->wasChanged(['path', 'alt', 'sort_order'])) {
                $syncListing($photo);
            }
        });
        static::deleted($syncListing);
    }

    protected $fillable = ['listing_id', 'path', 'thumbnail_path', 'alt', 'sort_order', 'width', 'height'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class, 'listing_id');
    }
}
