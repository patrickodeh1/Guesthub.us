<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaFolder extends Model
{
    protected $fillable = ['property_id', 'parent_id', 'name'];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id')->orderBy('name');
    }

    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'media_folder_id')->latest();
    }
}
