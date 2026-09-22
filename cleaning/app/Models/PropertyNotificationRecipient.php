<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyNotificationRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'phone_number',
        'recipient_name',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
