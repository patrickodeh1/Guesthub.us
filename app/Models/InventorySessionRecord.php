<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySessionRecord extends Model
{
    protected $fillable = [
        'property_id', 'cleaning_session_id', 'inventory_item_id', 'from_location_id',
        'guest_location_id', 'recorded_by', 'quantity_available', 'quantity_taken',
        'quantity_after_restock', 'quantity_guest_consumed', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_available' => 'decimal:3',
            'quantity_taken' => 'decimal:3',
            'quantity_after_restock' => 'decimal:3',
            'quantity_guest_consumed' => 'decimal:3',
        ];
    }

    public function cleaningSession(): BelongsTo
    {
        return $this->belongsTo(CleaningSession::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function guestLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'guest_location_id');
    }
}
