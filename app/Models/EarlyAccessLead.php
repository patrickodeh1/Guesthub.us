<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EarlyAccessLead extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'role', 'message', 'contacted_at'];

    protected function casts(): array
    {
        return ['contacted_at' => 'datetime'];
    }
}
