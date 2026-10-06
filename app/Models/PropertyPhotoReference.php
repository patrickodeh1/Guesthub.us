<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyPhotoReference extends Model
{
    protected $fillable = ['property_id', 'image_path', 'caption', 'sort_order'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function imageUrl(): string
    {
        return url('/img/' . $this->image_path);
    }
}
