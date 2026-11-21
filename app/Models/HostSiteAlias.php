<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostSiteAlias extends Model
{
    protected $fillable = ['host_site_id', 'slug'];

    public function hostSite(): BelongsTo
    {
        return $this->belongsTo(HostSite::class);
    }
}
