<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelSyncEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'listing_channel_link_id', 'listing_id', 'owner_id', 'channel', 'action', 'status', 'http_status', 'summary',
    ];

    protected function casts(): array
    {
        return ['summary' => 'array'];
    }
}
