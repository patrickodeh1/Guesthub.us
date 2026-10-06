<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A "dirty date" marker: this property+date+kind changed and must be sent to
 * Channex. Holds no values; the sender reads current values from
 * property_availabilities at send time.
 */
class ChannexOutbox extends Model
{
    protected $table = 'channex_outbox';

    protected $fillable = ['property_id', 'kind', 'date', 'attempts', 'next_attempt_at', 'last_error'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'next_attempt_at' => 'datetime',
        ];
    }
}
