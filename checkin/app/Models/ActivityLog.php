<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'actor_type',
        'actor_id',
        'actor_name',
        'actor_email',
        'action',
        'description',
        'icon',
        'module',
        'severity',
        'subject_type',
        'subject_id',
        'user_id',
        'property_id',
        'booking_id',
        'ip_address',
        'user_agent',
        'metadata',
        'old_values',
        'new_values',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function severityClass(): string
    {
        return match ($this->severity) {
            'success'  => 'badge-active',
            'warning'  => 'badge-pending',
            'danger'   => 'badge-id_uploaded',
            'security' => 'badge-security',
            default    => 'badge-inactive',
        };
    }

    public function severityIcon(): string
    {
        return match ($this->severity) {
            'success'  => 'check',
            'warning'  => 'sparkles',
            'danger'   => 'delete',
            'security' => 'security',
            default    => 'sparkles',
        };
    }

    public function actorLabel(): string
    {
        if ($this->actor_name) {
            return $this->actor_name;
        }
        return match ($this->actor_type) {
            'guest'  => 'Guest',
            'system' => 'System',
            default  => 'Admin',
        };
    }

    // ─── Backward-compatible static helper ───────────────────────────────────

    public static function record(string $action, string $description, ?string $icon = null, ?Model $subject = null): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        static::query()->create([
            'actor_type'   => $user ? 'admin' : 'system',
            'actor_id'     => $user?->id,
            'actor_name'   => $user?->name,
            'actor_email'  => $user?->email,
            'action'       => $action,
            'description'  => $description,
            'icon'         => $icon,
            'module'       => $icon,
            'severity'     => 'info',
            'subject_type' => $subject ? $subject::class : null,
            'subject_id'   => $subject?->getKey(),
            'user_id'      => $user?->id,
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);
    }
}
