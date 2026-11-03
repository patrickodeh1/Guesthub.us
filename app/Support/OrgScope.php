<?php

namespace App\Support;

use App\Models\User;

class OrgScope
{
    /**
     * The user, the account above them (owner or company) and the accounts below them.
     * Sibling accounts under the same company are not included.
     */
    public static function userIds(User $user): array
    {
        $ids = collect([$user->id]);

        if ($user->owner_id) {
            $ids->push($user->owner_id);
        }

        $ids = $ids->merge(User::where('owner_id', $user->id)->pluck('id'));

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * Tasks a user may see. Admin sees every task. Everyone else sees the admin's default tasks,
     * tasks they or their own team created, and tasks used on a property they can access.
     */
    public static function visibleTasks(User $user)
    {
        $query = \App\Models\Task::query();

        if ($user->hasRole('admin')) {
            return $query;
        }

        $visible = \App\Models\Property::query()->visibleTo($user)->select('properties.id');
        $org = self::userIds($user);
        $hasPropertyColumn = \Illuminate\Support\Facades\Schema::hasColumn('tasks', 'property_id');

        return $query->where(function ($w) use ($visible, $org, $hasPropertyColumn) {
            $w->where('tasks.is_default', true)
              ->orWhereIn('tasks.created_by', $org)
              ->orWhereHas('properties', fn ($p) => $p->whereIn('properties.id', $visible))
              ->orWhereHas('rooms.properties', fn ($p) => $p->whereIn('properties.id', $visible));

            if ($hasPropertyColumn) {
                $w->orWhereIn('tasks.property_id', $visible);
            }
        });
    }
}
