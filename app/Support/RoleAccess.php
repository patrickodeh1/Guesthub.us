<?php

namespace App\Support;

use App\Models\User;

class RoleAccess
{
    public static function canManageCategories(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public static function canManageTeam(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'owner', 'company']);
    }

    public static function canManageProperties(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'owner', 'company']);
    }

    public static function canManageSettings(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public static function canViewLogs(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'owner', 'company']);
    }

    public static function canManageGuests(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'owner', 'company', 'manager']);
    }

    public static function canDeleteData(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
