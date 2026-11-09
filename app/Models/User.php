<?php

namespace App\Models;

use App\Support\Branding;
use App\Support\PhoneFormatter;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ROLES = ['admin', 'company', 'owner', 'manager', 'housekeeper'];

    public const ROLE_LABELS = [
        'admin'   => 'Admin / Super Admin',
        'company' => 'Company',
        'owner'   => 'Property Owner',
        'manager' => 'Manager',
        'housekeeper' => 'Cleaner',
    ];

    public const ROLE_DESCRIPTIONS = [
        'admin'   => 'Full access to everything including users, settings, and all logs.',
        'company' => 'Cleaning company or property-management company. Manages its own listings, users and cleaners, and can reassign jobs among the workers it manages.',
        'owner'   => 'Owner or property manager. Manages their own properties, guests, bookings and cleaners. No access to global admin settings (logo, colours, global settings, roles).',
        'manager' => 'Property manager. Same scope as an owner: manages the properties they are linked to, but no access to global admin settings.',
        'housekeeper' => 'Can access assigned cleaning work and task operations.',
    ];

    protected $fillable = [
        'name',
        'host_name',
        'email',
        'phone_number',
        'profile_photo_path',
        'password',
        'status',
        'owner_id',
        'preferences',
        'must_change_password',
        'is_active',
        'terminated_at',
        'terminated_by',
        'termination_reason',
        'phone',
        'avatar',
        'admin_tour_completed_at',
        'dashboard_tour_completed_at',
        'full_system_tour_completed_at',
        'last_login_at',
        'last_login_ip',
        'created_by',
        'notes',
        'dismissed_notification_ids',
        'stripe_account_id',
        'stripe_charges_enabled',
        'stripe_payouts_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'            => 'datetime',
            'admin_tour_completed_at'      => 'datetime',
            'dashboard_tour_completed_at'  => 'datetime',
            'full_system_tour_completed_at' => 'datetime',
            'last_login_at'                => 'datetime',
            'password'                     => 'hashed',
            'must_change_password'         => 'boolean',
            'is_active'                    => 'boolean',
            'terminated_at'                => 'datetime',
            'dismissed_notification_ids'   => 'array',
            'preferences'                   => 'array',
            'stripe_charges_enabled'         => 'boolean',
            'stripe_payouts_enabled'         => 'boolean',
        ];
    }

    // ─── Notification helpers ───────────────────────────────────────────────

    /**
     * Mark a single notification key as dismissed/read for this admin.
     */
    public function dismissNotification(string $key): void
    {
        $dismissed = $this->dismissed_notification_ids ?? [];
        if (! in_array($key, $dismissed, true)) {
            $dismissed[] = $key;
            $this->update(['dismissed_notification_ids' => $dismissed]);
        }
    }

    /**
     * Mark all of the given notification keys as dismissed/read at once
     * (used by "mark all as read").
     */
    public function dismissNotifications(array $keys): void
    {
        $dismissed = array_values(array_unique(array_merge($this->dismissed_notification_ids ?? [], $keys)));
        $this->update(['dismissed_notification_ids' => $dismissed]);
    }

    public function hasNotificationDismissed(string $key): bool
    {
        return in_array($key, $this->dismissed_notification_ids ?? [], true);
    }

    // ─── Role helpers ────────────────────────────────────────────────────────

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isManager(): bool
    {
        return $this->hasAnyRole(['admin', 'manager']);
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(['admin', 'manager']);
    }

    public function canManageUsers(): bool
    {
        return \App\Support\RoleAccess::canManageTeam($this);
    }

    public function canManageSettings(): bool
    {
        return \App\Support\RoleAccess::canManageSettings($this);
    }

    public function canViewLogs(): bool
    {
        return \App\Support\RoleAccess::canViewLogs($this);
    }

    public function canManageProperties(): bool
    {
        return \App\Support\RoleAccess::canManageProperties($this);
    }

    public function canManageGuests(): bool
    {
        return \App\Support\RoleAccess::canManageGuests($this);
    }

    public function canDeleteData(): bool
    {
        return \App\Support\RoleAccess::canDeleteData($this);
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function properties(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'property_user', 'user_id', 'property_id');
    }

    public function hostFeatures(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HostFeature::class);
    }

    public function hostPaymentMethods(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HostPaymentMethod::class, 'owner_id');
    }

    public function managedOwners(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'housekeeper_owner', 'housekeeper_id', 'owner_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function deactivate(User $actor, string $reason): void
    {
        $this->forceFill([
            'is_active' => false,
            'terminated_at' => now(),
            'terminated_by' => $actor->getKey(),
            'termination_reason' => $reason,
        ])->save();
    }

    public function reactivate(): void
    {
        $this->forceFill([
            'is_active' => true,
            'terminated_at' => null,
            'terminated_by' => null,
            'termination_reason' => null,
        ])->save();
    }

    public function roleLabel(): string
    {
        $role = $this->getRoleNames()->first();

        return self::ROLE_LABELS[$role] ?? ucfirst((string) $role);
    }

    public function initials(): string
    {
        $parts = explode(' ', $this->name);
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1).substr(end($parts), 0, 1));
        }
        return strtoupper(substr($this->name, 0, 2));
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function terminator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): void
    {
        $query->where('is_active', false);
    }

    public function getFormattedPhoneAttribute(): ?string
    {
        return PhoneFormatter::format($this->phone);
    }

    /**
     * The host / business name used as the host party on guest agreements.
     * Prefers an owner's captured host_name, then any user's, then app name.
     */
    public static function agreementHostName(): string
    {
        $roleTable = config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $rolePivotKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $modelMorphKey = config('permission.column_names.model_morph_key', 'model_id');

        $hostName = static::query()
            ->whereNotNull('host_name')
            ->where('host_name', '!=', '')
            ->orderByRaw(
                "case when exists (
                    select 1 from {$modelHasRolesTable} as mhr
                    inner join {$roleTable} as r on r.id = mhr.{$rolePivotKey}
                    where mhr.{$modelMorphKey} = users.id
                      and mhr.model_type = ?
                      and r.name = ?
                ) then 0 else 1 end",
                [self::class, 'admin'],
            )
            ->value('host_name');

        return $hostName ?: Branding::siteName();
    }
}
