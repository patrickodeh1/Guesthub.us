<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'property_id', 'inventory_item_id', 'from_location_id', 'to_location_id',
        'recorded_by', 'cleaning_session_id', 'movement_type', 'quantity',
        'reference', 'notes', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'occurred_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'to_location_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function cleaningSession(): BelongsTo
    {
        return $this->belongsTo(CleaningSession::class, 'cleaning_session_id');
    }
}
