<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelConnection extends Model
{
    protected $fillable = [
        'owner_id', 'provider', 'channel', 'external_account_id', 'access_type',
        'status', 'display_name', 'meta', 'connected_at', 'last_error',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'connected_at' => 'datetime'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isUsable(): bool
    {
        return $this->status === 'connected';
    }
}
