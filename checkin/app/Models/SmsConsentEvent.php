<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SmsConsentEvent extends Model
{
    protected $fillable = [
        'booking_id',
        'phone',
        'event_type',
        'checkbox_status',
        'disclosure_text',
        'disclosure_version',
        'terms_version',
        'privacy_version',
        'page_url',
        'guest_name',
        'property_id',
        'host_name',
        'ip_address',
        'opt_in_method',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'checkbox_status' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
