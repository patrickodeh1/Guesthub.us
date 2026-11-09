<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingPhoto extends Model
{
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
