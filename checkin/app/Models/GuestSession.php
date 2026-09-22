<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestSession extends Model
{
    protected $fillable = ['booking_id', 'token_hash', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
