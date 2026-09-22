<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyRequest extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'request_type',
        'details',
        'status',
        'ip_address',
    ];
}
