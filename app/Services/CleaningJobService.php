<?php

namespace App\Services;

use App\Models\CleaningSession;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CleaningJobService
{
    public function create(User $actor, array $data): CleaningSession
    {
        $property = $this->property($actor, (int) $data['property_id']);
        $this->assertPropertyHasTasks($property);
        $this->assertAssignee($property, (int) $data['housekeeper_id']);
        $this->assertUniqueAssignment($data);

        return CleaningSession::create([
            'property_id' => $property->id,
            'owner_id' => $property->owner_id,
            'housekeeper_id' => $data['housekeeper_id'],
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'checkout_id' => $data['checkout_id'] ?? null,
            'sporadic_tasks' => array_values(array_unique($data['sporadic_tasks'] ?? [])),
        ]);
    }

    public function update(User $actor, CleaningSession $session, array $data): CleaningSession
    {
        abort_unless(
            Property::query()->visibleTo($actor)->whereKey($session->property_id)->exists(),
            403
        );

        $property = $this->property($actor, (int) $data['property_id']);
        $this->assertPropertyHasTasks($property);
        $this->assertAssignee($property, (int) $data['housekeeper_id']);
        $this->assertUniqueAssignment($data, $session);

        $data['owner_id'] = $property->owner_id;
        $data['sporadic_tasks'] = array_values(array_unique($data['sporadic_tasks'] ?? []));

        $data = $this->reopenRules($data, $session);

        $session->update($data);

        return $session->refresh();
    }

    public function updateStatus(User $actor, CleaningSession $session, string $status): CleaningSession
    {
        abort_unless(
            Property::query()->visibleTo($actor)->whereKey($session->property_id)->exists(),
            403
        );

        $data = $this->reopenRules(['status' => $status], $session);
        $session->update($data);

        return $session->refresh();
    }

    public function quickAssign(
        User $actor,
        array $data,
        ?CleaningSession $session = null
    ): CleaningSession {
        $payload = [
            'property_id' => $data['property_id'],
            'housekeeper_id' => $data['housekeeper_id'],
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'] ?? null,
            'status' => $session?->status ?? 'pending',
            'checkout_id' => $session?->checkout_id,
            'sporadic_tasks' => $session?->sporadic_tasks ?? [],
        ];

        return $session
            ? $this->update($actor, $session, $payload)
            : $this->create($actor, $payload);
    }

    private function reopenRules(array $data, CleaningSession $session): array
    {
        if ($data['status'] !== 'completed' && $session->status === 'completed' && $session->stage === 'summary') {
            $data['stage'] = 'rooms';
            $data['ended_at'] = null;
        }

        return $data;
    }

    private function property(User $actor, int $propertyId): Property
    {
        $property = Property::query()
            ->visibleTo($actor)
            ->with(['rooms.tasks', 'propertyTasks'])
            ->findOrFail($propertyId);

        if (! $property->owner_id) {
            throw ValidationException::withMessages([
                'property_id' => 'Set a property owner first. This property has no owner yet.',
            ]);
        }

        return $property;
    }

    private function assertPropertyHasTasks(Property $property): void
    {
        $taskCount = $property->rooms->flatMap->tasks->count() + $property->propertyTasks->count();

        if ($taskCount < 1) {
            throw ValidationException::withMessages([
                'property_id' => 'The selected property has no tasks defined. Please define rooms or property tasks before scheduling a session.',
            ]);
        }
    }

    private function assertAssignee(Property $property, int $userId): void
    {
        if (! DB::table('property_user')
            ->where('property_id', $property->id)
            ->where('user_id', $userId)
            ->exists()) {
            throw ValidationException::withMessages([
                'housekeeper_id' => 'Assignee must be explicitly assigned to this property.',
            ]);
        }
    }

    private function assertUniqueAssignment(array $data, ?CleaningSession $session = null): void
    {
        $duplicate = CleaningSession::query()
            ->where('property_id', $data['property_id'])
            ->where('housekeeper_id', $data['housekeeper_id'])
            ->whereDate('scheduled_date', $data['scheduled_date'])
            ->when($session, fn ($query) => $query->where('id', '<>', $session->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'scheduled_date' => 'Duplicate assignment for this housekeeper/property/date.',
            ]);
        }
    }
}
