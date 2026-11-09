<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostFeature extends Model
{
    protected $fillable = [
        'user_id',
        'feature_key',
        'enabled',
        'source',
        'price_cents',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'price_cents' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_key', 'key');
    }
}
