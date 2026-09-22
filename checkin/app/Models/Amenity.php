<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Amenity extends Model
{
    protected $fillable = ['property_id', 'title', 'icon', 'details', 'images', 'active'];

    protected function casts(): array
    {
        return ['images' => 'array', 'active' => 'boolean'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
