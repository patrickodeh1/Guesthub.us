<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentReceipt extends Model
{
    protected $fillable = ['payable_type', 'payable_id', 'role', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }
}
