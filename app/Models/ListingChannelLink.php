<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListingChannelLink extends Model
{
    protected $fillable = [
        'listing_id', 'channel', 'connection_id', 'external_listing_id', 'state', 'content_hash',
        'last_synced_at', 'sync_category', 'writable', 'status_checked_at', 'live', 'last_error', 'last_error_at', 'sections', 'locked_fields', 'warnings', 'remote_url',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'locked_fields' => 'array',
            'warnings' => 'array',
            'last_synced_at' => 'datetime',
            'writable' => 'boolean',
            'live' => 'boolean',
            'status_checked_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class, 'listing_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'connection_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ChannelSyncEvent::class, 'listing_channel_link_id');
    }
}
