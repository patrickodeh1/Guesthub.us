<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestNotice extends Model
{
    use HasFactory;

    public const TYPES = ['popup' => 'Pop-up', 'step' => 'Check-in/out step'];

    public const PHASES = [
        'any' => 'Check-in and check-out',
        'checkin' => 'Check-in only',
        'checkout' => 'Check-out only',
        'guide' => 'Guide / during stay',
    ];

    public const DAY_SCOPES = [
        'any' => 'Any day',
        'arrival_day' => 'Arrival day only',
        'checkout_day' => 'Check-out day only',
    ];

    protected $fillable = [
        'property_id',
        'title',
        'body',
        'type',
        'phase',
        'day_scope',
        'start_time',
        'end_time',
        'requires_parking',
        'active',
        'once_per_booking',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'requires_parking' => 'boolean',
            'active' => 'boolean',
            'once_per_booking' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Does this notice apply to the given booking right now? Evaluates the
     * property scope, phase, day scope, local-time window and parking
     * condition. Time windows that cross midnight (e.g. 23:00-04:00) are
     * handled.
     */
    public function matches(Booking $booking, string $phase): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->property_id !== null && (int) $this->property_id !== (int) $booking->property_id) {
            return false;
        }

        if ($this->phase !== 'any' && $this->phase !== $phase) {
            return false;
        }

        $today = now()->setTimezone(config('app.display_timezone'))->toDateString();

        if ($this->day_scope === 'arrival_day' && $booking->check_in_date?->toDateString() !== $today) {
            return false;
        }

        if ($this->day_scope === 'checkout_day' && $booking->check_out_date?->toDateString() !== $today) {
            return false;
        }

        if (! $this->matchesTimeWindow()) {
            return false;
        }

        if ($this->requires_parking !== null && (bool) $booking->parking_needed !== $this->requires_parking) {
            return false;
        }

        return true;
    }

    private function matchesTimeWindow(): bool
    {
        if (! $this->start_time && ! $this->end_time) {
            return true;
        }

        $now = now()->setTimezone(config('app.display_timezone'))->format('H:i:s');
        $start = $this->start_time ?: '00:00:00';
        $end = $this->end_time ?: '23:59:59';

        if ($start <= $end) {
            return $now >= $start && $now <= $end;
        }

        // Window crosses midnight (e.g. 23:00 -> 04:00).
        return $now >= $start || $now <= $end;
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
