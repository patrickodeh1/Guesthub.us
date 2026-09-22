<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['title', 'slug', 'icon', 'guest_icon', 'header_image', 'description', 'sort_order', 'active', 'is_global', 'action'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_global' => 'boolean'];
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_category')->withPivot(['custom_title', 'custom_description', 'header_image', 'active']);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CategoryPage::class);
    }
}
