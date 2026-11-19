<?php

namespace App\Support;

use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Which owner accounts' notification wording a user may view and edit.
 * Wording is stored per property owner (properties.owner_id). Admins also have the global defaults.
 * Access always comes from Property::visibleTo(), so no one can touch another account's settings.
 */
class NotificationScope
{
    /** @return array<int,string> owner user id => label */
    public static function editable(User $user): array
    {
        if (! $user->hasAnyRole(['admin', 'owner', 'company', 'manager'])) {
            return [];
        }

        $ids = collect();
        if ($user->hasAnyRole(['owner', 'company'])) {
            $ids->push((int) $user->id);
        }
        $ids = $ids->merge(
            Property::query()->visibleTo($user)->whereNotNull('properties.owner_id')->distinct()->pluck('properties.owner_id')
        )->map(fn ($i) => (int) $i)->unique()->values();
        if ($user->hasRole('admin')) {
            // The admin's own wording is the global entry, so never list the admin as a separate owner.
            $ids = $ids->reject(fn ($i) => $i === (int) $user->id)->values();
        }

        $names = User::query()->whereIn('id', $ids)->pluck('name', 'id');
        $out = [];
        foreach ($ids as $id) {
            $label = $names[$id] ?? ('Account #'.$id);
            $out[$id] = $id === (int) $user->id ? $label.' (you)' : $label;
        }
        asort($out);

        return $out;
    }

    /** @return array{ownerId: ?int, options: array<int,string>, isAdmin: bool} */
    public static function resolve(Request $request, User $user): array
    {
        $isAdmin = $user->hasRole('admin');
        $options = self::editable($user);
        $raw = $request->query('scope');
        if ($raw === null || $raw === '') {
            $raw = $isAdmin ? 'global' : (string) (array_key_first($options) ?? '');
        }
        if ($isAdmin && (string) $raw === (string) $user->id) {
            $raw = 'global';
        }
        if ($raw === 'global') {
            abort_unless($isAdmin, 403, 'Only an administrator can edit the global defaults.');

            return ['ownerId' => null, 'options' => $options, 'isAdmin' => true];
        }
        abort_unless(ctype_digit((string) $raw) && isset($options[(int) $raw]), 403, 'You cannot edit notification settings for that account.');

        return ['ownerId' => (int) $raw, 'options' => $options, 'isAdmin' => $isAdmin];
    }
}
