<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Property extends Model
{

    /**
     * Guest-facing short address: full street/city/state/zip in one line,
     * truncated immediately after the zip code (drops any trailing
     * country text like ", USA" and avoids re-printing city/state/zip
     * separately since $address already contains them).
     */
    public function shortAddress(): string
    {
        $addr = trim((string) $this->address);

        if ($addr === '') {
            return '';
        }

        // Strip a trailing country suffix such as ", USA" or " USA".
        $addr = preg_replace('/,?\s*USA$/i', '', $addr);

        // Truncate everything after the first ZIP code (5 digit, or ZIP+4).
        if (preg_match('/^.*?\d{5}(-\d{4})?/', $addr, $m)) {
            $addr = $m[0];
        }

        return rtrim(trim($addr), ", ");
    }

    use HasFactory;

    protected $fillable = [
        'name', 'unit_number', 'slug', 'address', 'city', 'state', 'zip', 'latitude', 'longitude', 'events_radius_miles', 'timezone', 'checkout_time', 'checkin_time',
        'map_embed_url', 'map_directions_url', 'contact_phone', 'contact_email',
        'welcome_intro', 'checkin_instructions', 'lockbox_code', 'parking_instructions',
        'requires_vehicle_photo',
        'checkout_instructions', 'header_image', 'active',
        'parking_rate_sunday', 'parking_rate_monday', 'parking_rate_tuesday',
        'parking_rate_wednesday', 'parking_rate_thursday', 'parking_rate_friday',
        'parking_rate_saturday',
        'early_checkin_rate_8am', 'early_checkin_rate_12pm',
        'early_checkin_rate_8am_12pm', 'early_checkin_rate_12pm_2pm', 'early_checkin_rate_2pm_4pm',
        'late_checkout_rate_authorized_hourly', 'late_checkout_rate_unauthorized_hourly',
        'late_checkout_rate_authorized_per_30min', 'late_checkout_rate_unauthorized_per_30min',
        'channex_property_id',
        'channex_room_type_id',
        'airbnb_ical_url',
        'deposit_cap_cents',
        'required_incidentals_hold_amount',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean', 'requires_vehicle_photo' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7',
            'parking_rate_sunday' => 'decimal:2', 'parking_rate_monday' => 'decimal:2',
            'parking_rate_tuesday' => 'decimal:2', 'parking_rate_wednesday' => 'decimal:2',
            'parking_rate_thursday' => 'decimal:2', 'parking_rate_friday' => 'decimal:2',
            'parking_rate_saturday' => 'decimal:2',
            'early_checkin_rate_8am' => 'decimal:2', 'early_checkin_rate_12pm' => 'decimal:2',
            'early_checkin_rate_8am_12pm' => 'decimal:2', 'early_checkin_rate_12pm_2pm' => 'decimal:2', 'early_checkin_rate_2pm_4pm' => 'decimal:2',
            'late_checkout_rate_authorized_hourly' => 'decimal:2',
            'late_checkout_rate_unauthorized_hourly' => 'decimal:2',
            'late_checkout_rate_authorized_per_30min' => 'decimal:2',
            'late_checkout_rate_unauthorized_per_30min' => 'decimal:2',
            'deposit_cap_cents' => 'integer',
            'required_incidentals_hold_amount' => 'decimal:2',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(PropertyAvailability::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'property_category')
            ->withPivot(['custom_title', 'custom_description', 'header_image', 'active'])
            ->withTimestamps()
            ->orderBy('categories.sort_order');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(CategoryPage::class);
    }

    public function amenities(): HasMany
    {
        return $this->hasMany(Amenity::class);
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->zip])->filter()->join(', ');
    }

    public function instructionSteps(): HasMany
    {
        return $this->hasMany(InstructionStep::class)->orderBy('sort_order');
    }

    public function locks(): HasMany
    {
        return $this->hasMany(PropertyLock::class);
    }

    /**
     * Get the configured parking rate for a given weekday.
     * Accepts a Carbon-like instance with ->dayOfWeek (0 = Sunday ... 6 = Saturday) or an int.
     */
    public function parkingRateForDay(\Carbon\Carbon|int $day): ?float
    {
        $dayOfWeek = $day instanceof \Carbon\Carbon ? $day->dayOfWeek : $day;

        $field = [
            0 => 'parking_rate_sunday',
            1 => 'parking_rate_monday',
            2 => 'parking_rate_tuesday',
            3 => 'parking_rate_wednesday',
            4 => 'parking_rate_thursday',
            5 => 'parking_rate_friday',
            6 => 'parking_rate_saturday',
        ][$dayOfWeek] ?? null;

        if (!$field || $this->{$field} === null) {
            return null;
        }

        return (float) $this->{$field};
    }

    public function heroImageUrl(): string
    {
        return $this->header_image
            ? url('/img/'.$this->header_image)
            : 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1400&q=80';
    }
}
