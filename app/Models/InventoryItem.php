<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'property_id', 'default_location_id', 'name', 'sku', 'barcode', 'category',
        'unit_of_measure', 'manufacturer', 'model', 'model_number', 'serial_number',
        'supplier', 'unit_cost', 'reorder_level', 'photo_path', 'last_ordered_at',
        'notes', 'active',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'reorder_level' => 'decimal:3',
            'last_ordered_at' => 'date',
            'low_stock_alerted_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'default_location_id');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function getQuantityOnHandAttribute(): float
    {
        return (float) ($this->stocks_sum_quantity ?? $this->stocks()->sum('quantity'));
    }

    public function getIsLowStockAttribute(): bool
    {
        return (float) $this->reorder_level > 0
            && $this->quantity_on_hand <= (float) $this->reorder_level;
    }
}
