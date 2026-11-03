<?php

namespace App\Http\Middleware;

use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\Room;
use App\Models\Task;
use App\Support\OrgScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every web request. If the matched route has a {session}, {cleaningSession},
 * {property}, {room} or {task} parameter, the signed-in user must be allowed to touch that
 * record. Admin always passes. Guests pass through (the auth middleware deals with them).
 * Because it keys on the parameter names, new routes are protected automatically.
 */
class EnforceResourceAccess
{
    private static bool $creatorCallbacksRegistered = false;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();

        if (! $user || ! $route || $user->hasRole('admin')) {
            return $next($request);
        }

        $this->recordCreators();

        $session = $this->model($route->parameter('session'), CleaningSession::class)
            ?? $this->model($route->parameter('cleaningSession'), CleaningSession::class);
        $property = $this->model($route->parameter('property'), Property::class);
        $room = $this->model($route->parameter('room'), Room::class);
        $task = $this->model($route->parameter('task'), Task::class);

        $visible = fn () => Property::query()->visibleTo($user)->select('properties.id');

        // Cleaning sessions: the assigned cleaner, or an owner/company/manager whose property it is.
        if ($session) {
            $ok = (int) $session->housekeeper_id === (int) $user->id
                || ($user->hasAnyRole(['owner', 'company', 'manager'])
                    && Property::query()->visibleTo($user)->whereKey($session->property_id)->exists());
            abort_unless($ok, 403, 'You do not have access to this cleaning session.');
        }

        // Properties, and the rooms and tasks addressed through them.
        if ($property) {
            abort_unless(
                Property::query()->visibleTo($user)->whereKey($property->id)->exists(),
                403,
                'You do not have access to this property.'
            );

            if ($room) {
                abort_unless(
                    $property->rooms()->where('rooms.id', $room->id)->exists(),
                    403,
                    'That room does not belong to this property.'
                );
            }

            if ($task) {
                $linked = $room
                    ? $room->tasks()->where('tasks.id', $task->id)->exists()
                    : ($task->properties()->where('properties.id', $property->id)->exists()
                        || (int) $task->getAttribute('property_id') === (int) $property->id
                        || $task->rooms()->whereHas('properties', fn ($p) => $p->where('properties.id', $property->id))->exists());
                abort_unless($linked, 403, 'That task does not belong to this property.');
            }
        }

        // Direct room/task routes must be scoped for reads as well as writes. A task addressed
        // through a room must also actually be attached to that room.
        if (! $session && ! $property) {
            if ($room) {
                $this->authorizeRoom($user, $room, $request->isMethodSafe(), $visible);
            }

            if ($task) {
                if ($room) {
                    abort_unless(
                        $room->tasks()->where('tasks.id', $task->id)->exists(),
                        403,
                        'That task does not belong to this room.'
                    );
                }

                $this->authorizeTask($user, $task, $request->isMethodSafe(), $visible);
            }
        }

        return $next($request);
    }

    // Files, folders and categories a non-admin creates are stamped with their user id, so each role only sees its own.
    private function recordCreators(): void
    {
        if (self::$creatorCallbacksRegistered) {
            return;
        }

        foreach ([\App\Models\MediaFile::class, \App\Models\MediaFolder::class, \App\Models\Category::class] as $class) {
            $class::creating(function ($model) {
                $actor = auth()->user();
                if ($actor && ! $actor->hasRole('admin') && empty($model->created_by)
                    && \Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), 'created_by')) {
                    $model->created_by = $actor->id;
                }
            });
        }

        self::$creatorCallbacksRegistered = true;
    }

    private function model(mixed $value, string $class): mixed
    {
        if ($value instanceof $class) {
            return $value;
        }

        return is_scalar($value) && $value !== '' ? $class::query()->find($value) : null;
    }

    private function authorizeRoom($user, Room $room, bool $readOnly, callable $visible): void
    {
        abort_if(! $readOnly && $room->is_default, 403, 'Default rooms can only be changed by an administrator.');

        if ($room->properties()->exists()) {
            abort_if(
                $room->properties()->whereNotIn('properties.id', $visible())->exists(),
                403,
                'You do not have access to this room.'
            );

            return;
        }

        abort_unless(
            in_array((int) $room->getAttribute('created_by'), OrgScope::userIds($user), true),
            403,
            'You do not have access to this room.'
        );
    }

    private function authorizeTask($user, Task $task, bool $readOnly, callable $visible): void
    {
        abort_if(
            ! $readOnly && ($task->is_default || $task->rooms()->where('rooms.is_default', true)->exists()),
            403,
            'Default tasks can only be changed by an administrator.'
        );

        $blocked = $task->rooms()
            ->whereHas('properties', fn ($query) => $query->whereNotIn('properties.id', $visible()))
            ->exists()
            || $task->properties()->whereNotIn('properties.id', $visible())->exists();

        $directPropertyId = $task->getAttribute('property_id');
        if ($directPropertyId) {
            $blocked = $blocked || ! Property::query()->visibleTo($user)->whereKey($directPropertyId)->exists();
        }

        abort_if($blocked, 403, 'You do not have access to this task.');
        abort_unless(
            OrgScope::visibleTasks($user)->whereKey($task->id)->exists(),
            403,
            'You do not have access to this task.'
        );
    }
}
