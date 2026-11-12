<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Charge extends Model
{
    use HasFactory;

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_PARKING = 'parking';

    public const TYPE_INCIDENTALS = 'incidentals';

    public const TYPE_EARLY_CHECKIN = 'early_checkin';

    public const TYPE_LATE_CHECKOUT = 'late_checkout';

    public const TYPE_ACCOMMODATION = 'accommodation';

    public const TYPE_APPLICATION_FEE = 'application_fee';

    public const TYPE_LEASE_INITIAL_PAYMENT = 'lease_initial_payment';

    public const STATUS_PENDING = 'pending';

    public const STATUS_HELD = 'held';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_PARTIALLY_CAPTURED = 'partially_captured';

    public const STATUS_RELEASED = 'released';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'booking_id', 'lease_application_id', 'lease_offer_id', 'lease_installment_id', 'type', 'amount_cents', 'status',
        'stripe_payment_intent_id', 'billing_moment',
        'captured_at', 'released_at', 'description', 'stripe_account_id', 'refunded_cents',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'captured_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_cents' => 'integer',
        ];
    }

    public function leaseOffer(): BelongsTo
    {
        return $this->belongsTo(LeaseOffer::class, 'lease_offer_id');
    }

    public function leaseInstallment(): BelongsTo
    {
        return $this->belongsTo(LeaseInstallment::class, 'lease_installment_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function leaseApplication(): BelongsTo
    {
        return $this->belongsTo(LeaseApplication::class);
    }

    public function amountDollars(): float
    {
        return $this->amount_cents / 100;
    }
}
