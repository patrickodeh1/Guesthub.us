<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostPackage extends Model
{
    /** Days features stay on after a failed charge before they switch off. */
    public const PAST_DUE_GRACE_DAYS = 7;

    protected $fillable = [
        'user_id', 'package_id', 'status', 'trial_ends_at', 'started_at',
        'billing_source', 'stripe_customer_id', 'stripe_subscription_id',
        'current_period_end', 'cancel_at_period_end', 'past_due_since',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'started_at' => 'datetime',
            'current_period_end' => 'datetime',
            'past_due_since' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** Active, in a running trial, or past due but still inside the grace period. */
    public function isGranting(): bool
    {
        if ($this->status === 'active') {
            return true;
        }
        if ($this->status === 'trialing') {
            return (bool) ($this->trial_ends_at && $this->trial_ends_at->isFuture());
        }
        if ($this->status === 'past_due') {
            return (bool) ($this->past_due_since && $this->past_due_since->gt(now()->subDays(self::PAST_DUE_GRACE_DAYS)));
        }

        return false;
    }
}
