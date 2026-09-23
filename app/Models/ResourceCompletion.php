<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceCompletion extends Model
{
    protected $fillable = [
        'user_id',
        'property_id',
        'completed_count',
        'last_completed_at',
    ];

    protected $casts = [
        'last_completed_at' => 'datetime',
        'completed_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Check if the user still needs mandatory onboarding for this property.
     */
    public function requiresOnboarding(): bool
    {
        return $this->completed_count < 3;
    }
}
