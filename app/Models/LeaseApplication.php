<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LeaseApplication extends Model
{
    protected $hidden = [
        'token',
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'id_name',
        'id_address',
        'id_number_last4',
        'id_scan_path',
        'pets',
        'vehicles',
        'current_address',
        'employer',
        'job_title',
        'supervisor_name',
        'supervisor_phone',
        'monthly_income_cents',
        'years_employed',
        'previous_landlord_name',
        'previous_landlord_phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'consent_signature',
        'consent_ip',
        'consent_user_agent',
        'host_notes',
        'decline_reason',
    ];

    protected $fillable = [
        'listing_id',
        'property_id',
        'status',
        'fee_status',
        'payment_method',
        'host_payment_method_id',
        'application_fee_cents',
        'processing_fee_cents',
        'total_fee_cents',
        'token',
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'id_name',
        'id_address',
        'id_number_last4',
        'id_scan_status',
        'id_scan_attempts',
        'id_scan_path',
        'move_in_date',
        'lease_term_months',
        'occupants',
        'pets',
        'vehicles',
        'current_address',
        'current_address_months',
        'employer',
        'job_title',
        'supervisor_name',
        'supervisor_phone',
        'monthly_income_cents',
        'years_employed',
        'previous_landlord_name',
        'previous_landlord_phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'consent_at',
        'consent_signature',
        'consent_ip',
        'consent_user_agent',
        'host_notes',
        'submitted_at',
        'declined_at',
        'screening_requested_at',
        'id_type',
        'id_back_scan_path',
        'fee_policy_accepted_at',
        'approved_at',
        'decline_reason',
        'sms_consent_at',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'applicant_name' => 'encrypted',
            'applicant_email' => 'encrypted',
            'applicant_phone' => 'encrypted',
            'id_name' => 'encrypted',
            'id_address' => 'encrypted',
            'id_number_last4' => 'encrypted',
            'pets' => 'encrypted',
            'vehicles' => 'encrypted',
            'current_address' => 'encrypted',
            'employer' => 'encrypted',
            'job_title' => 'encrypted',
            'supervisor_name' => 'encrypted',
            'supervisor_phone' => 'encrypted',
            'monthly_income_cents' => 'encrypted',
            'years_employed' => 'encrypted',
            'previous_landlord_name' => 'encrypted',
            'previous_landlord_phone' => 'encrypted',
            'emergency_contact_name' => 'encrypted',
            'emergency_contact_phone' => 'encrypted',
            'consent_signature' => 'encrypted',
            'consent_ip' => 'encrypted',
            'consent_user_agent' => 'encrypted',
            'host_notes' => 'encrypted',
            'decline_reason' => 'encrypted',
            'application_fee_cents' => 'integer',
            'processing_fee_cents' => 'integer',
            'total_fee_cents' => 'integer',
            'id_scan_attempts' => 'integer',
            'move_in_date' => 'date',
            'lease_term_months' => 'integer',
            'occupants' => 'integer',
            'current_address_months' => 'integer',
            'consent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'declined_at' => 'datetime',
            'screening_requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'sms_consent_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(PropertyListing::class, 'listing_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function hostPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(HostPaymentMethod::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(LeaseOffer::class)->latest('id');
    }

    /** The offer currently in play, or the most recent one. */
    public function offer(): HasOne
    {
        return $this->hasOne(LeaseOffer::class)->latestOfMany();
    }
}
