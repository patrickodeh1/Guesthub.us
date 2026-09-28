<?php

namespace App\Services;

use App\Models\Property;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class UnifiedActivityFeed
{
    public function paginate(array $filters, ?User $user = null, int $perPage = 30): LengthAwarePaginator
    {
        $query = $this->baseQuery($user);

        $query->when($filters['search'] ?? null, function (Builder $query, string $search): void {
            $like = '%'.$search.'%';
            $query->where(function (Builder $nested) use ($like): void {
                $nested->where('description', 'like', $like)
                    ->orWhere('actor_name', 'like', $like)
                    ->orWhere('actor_email', 'like', $like)
                    ->orWhere('event', 'like', $like)
                    ->orWhere('module', 'like', $like);
            });
        });

        $query->when($filters['source'] ?? null, fn (Builder $q, string $source) => $q->where('source', $source));
        $query->when($filters['actor'] ?? null, fn (Builder $q, string $actor) => $q->where('actor_type', $actor));
        $query->when($filters['module'] ?? null, fn (Builder $q, string $module) => $q->where('module', $module));
        $query->when($filters['severity'] ?? null, fn (Builder $q, string $severity) => $q->where('severity', $severity));
        $query->when($filters['property'] ?? null, fn (Builder $q, int $property) => $q->where('property_id', $property));
        $query->when($filters['subject_type'] ?? null, fn (Builder $q, string $type) => $q->where('subject_type', $this->subjectType($type)));
        $query->when($filters['subject_id'] ?? null, fn (Builder $q, int $id) => $q->where('subject_id', $id));
        $query->when($filters['date_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('occurred_at', '>=', $date));
        $query->when($filters['date_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('occurred_at', '<=', $date));

        return $query->orderByDesc('occurred_at')
            ->orderByDesc('source')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(string $source, string|int $id, ?User $user = null): ?object
    {
        $query = $this->baseQuery($user)
            ->where('source', $source)
            ->where('id', $id);

        return $query->first();
    }

    public function modules(): array
    {
        return DB::query()
            ->fromSub($this->baseQuery(), 'activity_feed')
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->all();
    }

    public function actors(): array
    {
        return DB::query()
            ->fromSub($this->baseQuery(), 'activity_feed')
            ->whereNotNull('actor_type')
            ->distinct()
            ->orderBy('actor_type')
            ->pluck('actor_type')
            ->all();
    }

    private function baseQuery(?User $user = null): Builder
    {
        $portal = DB::table('activity_logs')->selectRaw(
            "'portal' as source, CAST(id AS CHAR) as id, created_at as occurred_at,
             actor_name, actor_email, actor_type, action as event, description, module,
             icon,
             severity, subject_type, subject_id, property_id, booking_id,
             ip_address, user_agent, metadata as detail_payload,
             old_values, new_values, NULL as batch_uuid"
        );

        $ops = DB::table('activity_log')->selectRaw(
            "'ops' as source, CAST(activity_log.id AS CHAR) as id, activity_log.created_at as occurred_at,
             users.name as actor_name, users.email as actor_email, 'staff' as actor_type,
             activity_log.event as event, activity_log.description, activity_log.log_name as module,
             NULL as icon,
             CASE WHEN activity_log.event = 'deleted' THEN 'warning' ELSE 'info' END as severity,
             activity_log.subject_type, activity_log.subject_id,
             CASE WHEN activity_log.subject_type = ? THEN activity_log.subject_id ELSE NULL END as property_id,
             NULL as booking_id, NULL as ip_address, NULL as user_agent,
             activity_log.properties as detail_payload, NULL as old_values, NULL as new_values,
             activity_log.batch_uuid",
            [\App\Models\Property::class]
        )->leftJoin('users', function ($join): void {
            $join->on('users.id', '=', 'activity_log.causer_id')
                ->where('activity_log.causer_type', '=', User::class);
        });

        $query = DB::query()->fromSub($portal->unionAll($ops), 'activity_feed');

        if ($user?->hasRole('owner') && ! $user->hasAnyRole(['admin', 'manager'])) {
            $propertyIds = Property::query()->where('owner_id', $user->id)->pluck('id');
            $query->whereIn('property_id', $propertyIds);
        } elseif ($user && ! $user->hasAnyRole(['admin', 'manager'])) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function subjectType(string $type): string
    {
        if (str_contains($type, '\\')) {
            return $type;
        }

        return [
            'Property' => \App\Models\Property::class,
            'Room' => \App\Models\Room::class,
            'Task' => \App\Models\Task::class,
            'CleaningSession' => \App\Models\CleaningSession::class,
            'ChecklistItem' => \App\Models\ChecklistItem::class,
        ][$type] ?? $type;
    }
}
