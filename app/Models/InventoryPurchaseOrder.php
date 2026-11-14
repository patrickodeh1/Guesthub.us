<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryPurchaseOrder extends Model
{
    protected $fillable = [
        'property_id', 'created_by', 'supplier', 'reference', 'status', 'ordered_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['ordered_at' => 'date'];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryPurchaseOrderLine::class, 'purchase_order_id');
    }
}
