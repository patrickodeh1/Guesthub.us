<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CleaningSession;
use App\Models\Property;

class CleaningSessionPolicy
{
    /**
     * Determine whether the user can view the cleaning session.
     */
    public function view(User $user, CleaningSession $session): bool
    {
        if ($session->housekeeper_id === $user->id || $user->hasRole('admin')) {
            return true;
        }

        return $user->hasAnyRole(['owner', 'company', 'manager'])
            && Property::query()->visibleTo($user)->whereKey($session->property_id)->exists();
    }
}
