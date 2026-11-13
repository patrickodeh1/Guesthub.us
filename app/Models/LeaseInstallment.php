<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaseInstallment extends Model
{
    public const KIND_DEPOSIT = 'deposit';

    public const KIND_RENT = 'rent';

    public const KIND_FEE = 'fee';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_WAIVED = 'waived';

    protected $fillable = [
        'lease_offer_id',
        'kind',
        'label',
        'due_date',
        'amount_cents',
        'required_at_signing',
        'status',
        'method',
        'charge_id',
        'paid_at',
        'receipt_path',
        'received_by',
        'reminded_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount_cents' => 'integer',
            'required_at_signing' => 'boolean',
            'paid_at' => 'datetime',
            'reminded_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(LeaseOffer::class, 'lease_offer_id');
    }

    public function charges()
    {
        return $this->hasMany(Charge::class, 'lease_installment_id');
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isSettled(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_WAIVED], true);
    }
}
