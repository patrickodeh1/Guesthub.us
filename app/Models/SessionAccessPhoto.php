<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionAccessPhoto extends Model
{
    protected $fillable = ['cleaning_session_id', 'instruction_step_id', 'path', 'captured_at'];

    protected $casts = ['captured_at' => 'datetime'];
}
