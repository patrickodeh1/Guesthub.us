<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaseOffer extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $hidden = [
        'token',
        'host_signed_ip',
        'host_signed_user_agent',
        'guest_signed_ip',
        'guest_signed_user_agent',
    ];

    protected $fillable = [
        'lease_application_id',
        'property_id',
        'booking_id',
        'status',
        'token',
        'start_date',
        'end_date',
        'monthly_rent_cents',
        'deposit_cents',
        'fees',
        'total_cents',
        'initial_due_cents',
        'payment_method',
        'host_payment_method_id',
        'accept_by',
        'contract_html',
        'contract_version',
        'term_months',
        'currency',
        'guest_payment_reference',
        'host_signed_name',
        'host_signed_at',
        'host_signed_ip',
        'host_signed_user_agent',
        'guest_signed_name',
        'guest_signed_at',
        'guest_signed_ip',
        'guest_signed_user_agent',
        'guest_signed_device_id',
        'sent_at',
        'reminded_at',
        'confirmed_at',
        'declined_at',
        'declined_reason',
        'expired_at',
        'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'monthly_rent_cents' => 'integer',
            'deposit_cents' => 'integer',
            'total_cents' => 'integer',
            'initial_due_cents' => 'integer',
            'term_months' => 'integer',
            'fees' => 'array',
            'accept_by' => 'datetime',
            'host_signed_at' => 'datetime',
            'guest_signed_at' => 'datetime',
            'sent_at' => 'datetime',
            'reminded_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'declined_at' => 'datetime',
            'expired_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'host_signed_name' => 'encrypted',
            'host_signed_ip' => 'encrypted',
            'host_signed_user_agent' => 'encrypted',
            'guest_signed_name' => 'encrypted',
            'guest_signed_ip' => 'encrypted',
            'guest_signed_user_agent' => 'encrypted',
            'declined_reason' => 'encrypted',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LeaseApplication::class, 'lease_application_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function hostPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(HostPaymentMethod::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(LeaseInstallment::class)->orderBy('due_date')->orderBy('sort_order')->orderBy('id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class, 'lease_offer_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /** Draft, sent or signed: the offer is still in play. */
    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SENT, self::STATUS_SIGNED], true);
    }

    /** Sent or signed offers hold the dates on the calendar. */
    public function holdsDates(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_SIGNED], true);
    }

    public function isFullySigned(): bool
    {
        return $this->host_signed_at !== null && $this->guest_signed_at !== null;
    }

    public function nights(): int
    {
        if (! $this->start_date || ! $this->end_date) {
            return 0;
        }

        return (int) $this->start_date->copy()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay());
    }
}
