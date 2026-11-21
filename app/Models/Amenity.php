<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Amenity extends Model
{
    protected static function booted(): void
    {
        $sync = static function (self $amenity): void {
            $listingId = PropertyListing::query()->where('property_id', $amenity->property_id)->value('id');
            if ($listingId) {
                PropertyListing::queueChannelContentSync((int) $listingId);
            }
        };

        static::created($sync);
        static::updated(function (self $amenity) use ($sync): void {
            if ($amenity->wasChanged(['active', 'repull_amenity_id', 'title'])) {
                $sync($amenity);
            }
        });
        static::deleted($sync);
    }

    protected $fillable = ['property_id', 'title', 'icon', 'details', 'images', 'active', 'repull_amenity_id'];

    protected function casts(): array
    {
        return ['images' => 'array', 'active' => 'boolean'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
