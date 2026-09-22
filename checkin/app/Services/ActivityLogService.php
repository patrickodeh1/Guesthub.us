<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    public static function log(array $data): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        ActivityLog::create([
            'actor_type'   => $data['actor_type'] ?? ($user ? 'admin' : 'system'),
            'actor_id'     => $data['actor_id'] ?? $user?->id,
            'actor_name'   => $data['actor_name'] ?? $user?->name,
            'actor_email'  => $data['actor_email'] ?? $user?->email,
            'action'       => $data['action'],
            'description'  => $data['description'],
            'icon'         => $data['icon'] ?? $data['module'] ?? null,
            'module'       => $data['module'] ?? null,
            'severity'     => $data['severity'] ?? 'info',
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id'   => $data['subject_id'] ?? null,
            'property_id'  => $data['property_id'] ?? null,
            'booking_id'   => $data['booking_id'] ?? null,
            'user_id'      => $user?->id,
            'ip_address'   => $data['ip_address'] ?? Request::ip(),
            'user_agent'   => $data['user_agent'] ?? Request::userAgent(),
            'metadata'     => $data['metadata'] ?? null,
            'old_values'   => $data['old_values'] ?? null,
            'new_values'   => $data['new_values'] ?? null,
        ]);
    }

    public static function admin(string $action, string $description, string $module, array $extra = []): void
    {
        static::log(array_merge([
            'actor_type' => 'admin',
            'action'     => $action,
            'module'     => $module,
            'description' => $description,
            'severity'   => 'info',
        ], $extra));
    }

    public static function guest(string $action, string $description, string $module, array $extra = []): void
    {
        static::log(array_merge([
            'actor_type' => 'guest',
            'action'     => $action,
            'module'     => $module,
            'description' => $description,
            'severity'   => 'info',
        ], $extra));
    }

    public static function security(string $action, string $description, array $extra = []): void
    {
        static::log(array_merge([
            'action'     => $action,
            'module'     => 'auth',
            'description' => $description,
            'severity'   => 'security',
        ], $extra));
    }

    public static function warning(string $action, string $description, string $module, array $extra = []): void
    {
        static::log(array_merge([
            'action'     => $action,
            'module'     => $module,
            'description' => $description,
            'severity'   => 'warning',
        ], $extra));
    }

    public static function success(string $action, string $description, string $module, array $extra = []): void
    {
        static::log(array_merge([
            'action'     => $action,
            'module'     => $module,
            'description' => $description,
            'severity'   => 'success',
        ], $extra));
    }
}
