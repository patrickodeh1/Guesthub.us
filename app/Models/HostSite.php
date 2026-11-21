<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostSite extends Model
{
    protected $fillable = [
        'user_id', 'slug', 'display_name', 'logo_path', 'brand_color',
        'support_email', 'show_powered_by',
    ];

    protected function casts(): array
    {
        return ['show_powered_by' => 'boolean'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(HostSiteAlias::class);
    }
}
