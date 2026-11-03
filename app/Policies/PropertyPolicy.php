<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    /**
     * Determine if the user can deactivate the property.
     */
    public function deactivate(User $user, Property $property): bool
    {
        return $this->manages($user, $property);
    }

    /**
     * Determine if the user can activate the property.
     */
    public function activate(User $user, Property $property): bool
    {
        return $this->manages($user, $property);
    }

    /**
     * Determine if the user can view inactive properties.
     */
    public function viewInactive(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'company', 'owner', 'manager']);
    }

    /** Admin: always. Owner/company/manager: only properties Property::visibleTo() allows for them. */
    private function manages(User $user, Property $property): bool
    {
        if ($user->hasRole('admin') || $user->id === $property->owner_id) {
            return true;
        }

        if (! $user->hasAnyRole(['owner', 'company'])) {
            return false;
        }

        return Property::query()->visibleTo($user)->whereKey($property->id)->exists();
    }
}
