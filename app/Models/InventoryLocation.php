<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryLocation extends Model
{
    protected $fillable = ['property_id', 'name', 'notes'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }
}
