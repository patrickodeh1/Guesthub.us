<?php

namespace App\Policies;

use App\Models\InstructionalVideo;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InstructionalVideoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, InstructionalVideo $video): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        // Creator of the video can view it
        if ($video->created_by === $user->id) {
            return true;
        }

        // Allow viewing if the user has access to any of the properties assigned to the video
        return $video->properties()
            ->where(function ($query) use ($user) {
                if ($user->hasAnyRole(['owner', 'company'])) {
                    $query->where('owner_id', $user->id);
                } else {
                    $query->whereIn('properties.id', function ($sub) use ($user) {
                        $sub->select('property_id')
                            ->from('property_user')
                            ->where('user_id', $user->id);
                    })->orWhereIn('properties.id', function ($sub) use ($user) {
                        $sub->select('property_id')
                            ->from('cleaning_sessions')
                            ->where('housekeeper_id', $user->id);
                    });
                }
            })->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'owner', 'company']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, InstructionalVideo $video): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $video->created_by === $user->id && $user->hasAnyRole(['owner', 'company']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, InstructionalVideo $video): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $video->created_by === $user->id && $user->hasAnyRole(['owner', 'company']);
    }

    /**
     * Determine whether the user can publish the model.
     */
    public function publish(User $user, InstructionalVideo $video): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $video->created_by === $user->id && $user->hasAnyRole(['owner', 'company']);
    }
}
