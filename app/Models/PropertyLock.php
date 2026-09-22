<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'seam_device_id',
        'label',
        'manufacturer',
        'last_known_locked',
        'last_status_at',
        'battery_level',
    ];

    protected function casts(): array
    {
        return [
            'last_known_locked' => 'boolean',
            'last_status_at' => 'datetime',
        ];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
