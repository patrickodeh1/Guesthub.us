<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    protected $fillable = ['key', 'name', 'is_free', 'price_cents', 'active', 'billing_unit'];

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'price_cents' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function hostFeatures(): HasMany
    {
        return $this->hasMany(HostFeature::class, 'feature_key', 'key');
    }
}
