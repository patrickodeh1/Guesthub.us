<?php

namespace App\Models;

use App\Support\PhoneFormatter;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLES = ['owner', 'manager', 'staff', 'viewer'];

    public const ROLE_LABELS = [
        'owner'   => 'Owner / Super Admin',
        'manager' => 'Manager',
        'staff'   => 'Staff',
        'viewer'  => 'Viewer',
    ];

    public const ROLE_DESCRIPTIONS = [
        'owner'   => 'Full access to everything including users, settings, and all logs.',
        'manager' => 'Can manage properties, guests, categories and view logs. Cannot manage users or settings.',
        'staff'   => 'Can view guests and update guest status. Limited access.',
        'viewer'  => 'Read-only access across the admin panel.',
    ];

    protected $fillable = [
        'name',
        'host_name',
        'email',
        'password',
        'role',
        'status',
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
            'dismissed_notification_ids'   => 'array',
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
        return $this->role === 'owner';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['owner', 'manager'], true);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['owner', 'manager', 'staff'], true);
    }

    public function canManageUsers(): bool
    {
        return $this->role === 'owner';
    }

    public function canManageSettings(): bool
    {
        return $this->role === 'owner';
    }

    public function canViewLogs(): bool
    {
        return in_array($this->role, ['owner', 'manager'], true);
    }

    public function canManageProperties(): bool
    {
        return in_array($this->role, ['owner', 'manager'], true);
    }

    public function canManageGuests(): bool
    {
        return in_array($this->role, ['owner', 'manager', 'staff'], true);
    }

    public function canDeleteData(): bool
    {
        return $this->role === 'owner';
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? ucfirst($this->role);
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
        $hostName = static::query()
            ->whereNotNull('host_name')
            ->where('host_name', '!=', '')
            ->orderByRaw("case when role = 'owner' then 0 else 1 end")
            ->value('host_name');

        return $hostName ?: config('app.name');
    }
}
