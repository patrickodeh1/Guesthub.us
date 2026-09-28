<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CleaningSession;

class CleaningSessionPolicy
{
    /**
     * Determine whether the user can view the cleaning session.
     */
    public function view(User $user, CleaningSession $session): bool
    {
        return $session->housekeeper_id === $user->id 
            || $user->hasAnyRole(['admin', 'owner', 'company']);
    }
}
