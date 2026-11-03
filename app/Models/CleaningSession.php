<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CleaningSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'owner_id',
        'housekeeper_id',
        'scheduled_date',
        'scheduled_time',
        'status',
        'started_at',
        'ended_at',
        'gps_confirmed_at',
        'start_latitude',
        'start_longitude',
        'skipped_rooms',
        'report_token',
        'stage',
        'checkout_id',
        'booking_id',
        'no_guest',
        'assignment_status',
        'schedule_overridden',
        'cleaner_notified_at',
        'sporadic_tasks',
        'parking_needed',
        'photo_tutorial_viewed_at',
        'gps_override_enabled',
        'gps_override_approved_by',
        'gps_override_reason',
        'gps_override_timestamp',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'gps_confirmed_at' => 'datetime',
        'skipped_rooms' => 'array',
        'sporadic_tasks' => 'array',
        'gps_override_enabled' => 'boolean',
        'gps_override_timestamp' => 'datetime',
        'no_guest' => 'boolean',
        'parking_needed' => 'boolean',
        'photo_tutorial_viewed_at' => 'datetime',
        'cleaner_notified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if (empty($session->report_token)) {
                $session->report_token = self::newReportToken();
            }
        });

        static::created(function (self $session): void {
            (new \App\Services\TrainingService())->generateSnapshotsForSession($session);
        });

        static::updated(function (self $session): void {
            if ($session->wasChanged(['property_id', 'housekeeper_id'])) {
                (new \App\Services\TrainingService())->generateSnapshotsForSession($session);
            }
        });
    }

    public static function newReportToken(): string
    {
        return Str::lower((string) Str::ulid());
    }

    public function ensureReportToken(): string
    {
        if (!empty($this->report_token)) {
            return $this->report_token;
        }

        do {
            $token = self::newReportToken();
            $exists = self::where('report_token', $token)->exists();
        } while ($exists);

        $this->forceFill(['report_token' => $token])->saveQuietly();

        return $token;
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Property::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_id');
    }

    public function housekeeper(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'housekeeper_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'session_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RoomPhoto::class, 'session_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ChecklistReport::class, 'session_id');
    }

    public function gpsOverrideApprovedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'gps_override_approved_by');
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PropertyCheckout::class, 'checkout_id');
    }

    protected $guestBookingCache = false;

    /**
     * The guest stay tied to this cleaning: the explicit booking link first,
     * else a same-property booking checking out on the cleaning date.
     */
    public function guestBooking(): ?\App\Models\Booking
    {
        if ($this->guestBookingCache !== false) {
            return $this->guestBookingCache;
        }
        $b = $this->booking;
        if (! $b && $this->property_id && $this->scheduled_date) {
            $b = \App\Models\Booking::query()
                ->where('property_id', $this->property_id)
                ->whereDate('check_out_date', $this->scheduled_date)
                ->whereNull('cancelled_at')
                ->orderByDesc('id')
                ->first();
        }
        return $this->guestBookingCache = $b;
    }

    /** @return array{guest: ?string, stay: ?string, booking: ?\App\Models\Booking} */
    public function guestInfo(): array
    {
        $b = $this->guestBooking();
        if ($b) {
            return ['guest' => $b->guest_name, 'stay' => $b->stayRangeLabel(), 'booking' => $b];
        }
        $c = $this->checkout;
        if ($c && $c->guest_name) {
            return [
                'guest' => $c->guest_name,
                'stay' => $c->checkout_date ? 'Check-out ' . \Illuminate\Support\Carbon::parse($c->checkout_date)->format('M j, Y') : null,
                'booking' => null,
            ];
        }
        return ['guest' => null, 'stay' => null, 'booking' => null];
    }

    /** "Guest Name · stay dates" or null when no guest is tied to this cleaning. */
    public function guestLabel(): ?string
    {
        $i = $this->guestInfo();
        if (! $i['guest']) {
            return null;
        }
        return trim($i['guest'] . ($i['stay'] ? ' · ' . $i['stay'] : ''));
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Booking::class);
    }

    public function assignmentTrainingSnapshots(): HasMany
    {
        return $this->hasMany(AssignmentTrainingSnapshot::class, 'cleaning_session_id');
    }
}
