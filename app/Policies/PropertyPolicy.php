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
        return $user->hasAnyRole(['admin', 'company']) || $user->id === $property->owner_id;
    }

    /**
     * Determine if the user can activate the property.
     */
    public function activate(User $user, Property $property): bool
    {
        return $user->hasAnyRole(['admin', 'company']) || $user->id === $property->owner_id;
    }

    /**
     * Determine if the user can view inactive properties.
     */
    public function viewInactive(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'company', 'owner']);
    }
}
