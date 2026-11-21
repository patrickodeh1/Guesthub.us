<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingRoom extends Model
{
    protected $fillable = [
        'listing_id', 'room_type', 'room_number', 'room_name',
        'bed_type', 'bed_quantity', 'is_private',
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
