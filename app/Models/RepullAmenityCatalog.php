<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepullAmenityCatalog extends Model
{
    protected $table = 'repull_amenity_catalog';

    protected $fillable = ['label', 'canonical_id', 'category', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
