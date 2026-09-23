<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'user_id',
        'cleaning_session_id',
        'notification_type',
        'recipient_phone',
        'recipient_email',
        'message_content',
        'delivery_status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function cleaningSession(): BelongsTo
    {
        return $this->belongsTo(CleaningSession::class);
    }
}
