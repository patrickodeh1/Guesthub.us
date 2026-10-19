<?php

namespace App\Services;

use App\Exceptions\ConflictsDetected;
use App\Models\Booking;
use App\Models\CleaningSession;
use Illuminate\Support\Carbon;
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
            'booking_id' => $data['booking_id'] ?? null,
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
        ?CleaningSession $session = null,
        bool $confirmConflicts = false
    ): CleaningSession {
        $cleaner = User::find($data['housekeeper_id'] ?? null);
        if ($cleaner && ! ($cleaner->phone_number ?: $cleaner->phone)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'housekeeper_id' => "{$cleaner->name} has no phone number on file, so they can't be texted this assignment. Add a phone number to their profile first.",
            ]);
        }

        $checkout = $this->assertSchedulable($data);

        if (! $confirmConflicts) {
            $conflicts = $this->detectConflicts($data, $session);
            if ($conflicts) {
                throw new ConflictsDetected($conflicts);
            }
        }

        $payload = [
            'property_id' => $data['property_id'],
            'housekeeper_id' => $data['housekeeper_id'],
            'scheduled_date' => $data['scheduled_date'],
            'scheduled_time' => $data['scheduled_time'] ?? null,
            'status' => $data['status'] ?? $session?->status ?? 'pending',
            'checkout_id' => $session?->checkout_id,
            'booking_id' => $session?->booking_id ?? ($data['booking_id'] ?? null),
            'sporadic_tasks' => array_key_exists('sporadic_tasks', $data) ? ($data['sporadic_tasks'] ?? []) : ($session?->sporadic_tasks ?? []),
        ];

        if (! $session) {
            $d = Carbon::parse($data['scheduled_date'])->toDateString();
            $session = CleaningSession::where('property_id', $data['property_id'])
                ->whereNotNull('booking_id')
                ->whereNull('housekeeper_id')
                ->where('status', 'pending')
                ->whereIn('booking_id', Booking::where('property_id', $data['property_id'])
                    ->whereNull('cancelled_at')
                    ->whereDate('check_in_date', '<=', $d)
                    ->whereDate('check_out_date', '>=', $d)
                    ->select('id'))
                ->orderByRaw('DATE(scheduled_date) = ? desc', [$d])
                ->first();
        }

        $before = $session ? [
            'hk' => (int) $session->housekeeper_id,
            'date' => $session->scheduled_date?->toDateString(),
            'time' => $session->scheduled_time?->format('H:i'),
        ] : null;

        $result = $session
            ? $this->update($actor, $session, $payload)
            : $this->create($actor, $payload);

        $custom = $checkout
            && ! empty($data['scheduled_time'])
            && Carbon::parse($data['scheduled_date'].' '.$data['scheduled_time'])->ne($checkout);
        $moved = false;
        if ($result->booking_id && ($lb = Booking::find($result->booking_id)) && $lb->check_out_date) {
            $moved = $lb->check_out_date->toDateString() !== $result->scheduled_date?->toDateString();
        }
        $result->forceFill(['schedule_overridden' => (bool) $custom || $moved])->saveQuietly();

        // Same cleaner, new date or time: the model hook only fires on a cleaner change, so re-text here.
        if ($before && $before['hk'] === (int) $result->housekeeper_id && $before['hk'] > 0) {
            $changed = $before['date'] !== $result->scheduled_date?->toDateString()
                || $before['time'] !== $result->scheduled_time?->format('H:i');
            if ($changed) {
                \App\Services\CleaningFlowService::notifyAssigned($result->fresh(), true);
            }
        }

        return $result;
    }

    public const MIN_TURNOVER_MINUTES = 240;

    /**
     * Hard rules, not overridable. Returns the departing guest's effective
     * checkout, or null when no guest checks out that day at that property.
     */
    private function assertSchedulable(array $data): ?Carbon
    {
        $date = Carbon::parse($data['scheduled_date'])->toDateString();
        $dead = ['cancelled', 'canceled', 'declined', 'rejected', 'expired', 'no_show'];
        $live = fn ($q) => $q->whereNull('cancelled_at')
            ->where(fn ($w) => $w->whereNull('status')->orWhereNotIn('status', $dead));
        $fail = function (string $m) {
            throw \Illuminate\Validation\ValidationException::withMessages(['scheduled_time' => $m]);
        };

        $out = Booking::query()->where('property_id', (int) $data['property_id'])
            ->whereDate('check_out_date', $date)->where($live)->first();
        if (! $out) {
            return null;
        }

        if ($out->checkout_time_status === 'pending') {
            $fail("This guest's checkout time request is still pending. Approve or decline it before scheduling a cleaner.");
        }

        $checkout = Carbon::parse($date.' '.$out->effectiveCheckoutTime());
        $start = ! empty($data['scheduled_time'])
            ? Carbon::parse($date.' '.$data['scheduled_time'])
            : $checkout->copy();

        if ($start->lt($checkout)) {
            $fail("A cleaning can't start before the guest's checkout at ".$checkout->format('g:i A').'.');
        }

        $in = Booking::query()->where('property_id', (int) $data['property_id'])
            ->whereDate('check_in_date', $date)->where('id', '<>', $out->id)->where($live)->first();
        if ($in) {
            if ($in->checkin_time_status === 'pending') {
                $fail("The next guest's check-in time request is still pending. Approve or decline it before scheduling a cleaner.");
            }
            $arrive = Carbon::parse($date.' '.$in->effectiveCheckinTime());
            if ($start->diffInMinutes($arrive, false) < self::MIN_TURNOVER_MINUTES) {
                $fail('The next guest arrives at '.$arrive->format('g:i A').', which leaves less than '
                    .(self::MIN_TURNOVER_MINUTES / 60).' hours after a '.$start->format('g:i A').' start.');
            }
        }

        return $checkout;
    }

    public const CLEANER_OVERLAP_MINUTES = 180;

    /**
     * Soft conflicts an admin can override. Exact duplicates stay a hard block
     * in assertUniqueAssignment().
     *
     * @return array<int, array{type: string, message: string}>
     */
    public function detectConflicts(array $data, ?CleaningSession $session = null): array
    {
        $date = Carbon::parse($data['scheduled_date'])->toDateString();
        $time = $data['scheduled_time'] ?? null;
        $propertyId = (int) $data['property_id'];
        $cleanerId = (int) $data['housekeeper_id'];
        $day = Carbon::parse($date)->format('M j');
        $conflicts = [];

        $mins = fn ($t) => $t ? ((int) Carbon::parse($t)->format('H') * 60 + (int) Carbon::parse($t)->format('i')) : null;
        $fmt = fn ($t) => $t ? Carbon::parse($t)->format('g:i A') : 'no set time';

        $others = CleaningSession::query()
            ->whereDate('scheduled_date', $date)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when($session, fn ($q) => $q->where('id', '<>', $session->id))
            ->where(fn ($q) => $q->where('housekeeper_id', $cleanerId)->orWhere('property_id', $propertyId))
            ->get();

        $properties = Property::query()
            ->whereIn('id', $others->pluck('property_id')->push($propertyId)->unique())
            ->pluck('name', 'id');
        $users = User::query()
            ->whereIn('id', $others->pluck('housekeeper_id')->push($cleanerId)->unique())
            ->pluck('name', 'id');

        $newMins = $mins($time);

        foreach ($others as $other) {
            $otherMins = $mins($other->scheduled_time);

            if ((int) $other->housekeeper_id === $cleanerId && (int) $other->property_id !== $propertyId) {
                if ($newMins === null || $otherMins === null || abs($newMins - $otherMins) < self::CLEANER_OVERLAP_MINUTES) {
                    $conflicts[] = [
                        'type' => 'cleaner_busy',
                        'message' => ($users[$cleanerId] ?? 'This cleaner').' is already assigned to '
                            .($properties[$other->property_id] ?? 'another property')." on {$day} at ".$fmt($other->scheduled_time).'.',
                    ];
                }
            }

            if ((int) $other->property_id === $propertyId && (int) $other->housekeeper_id !== $cleanerId) {
                $conflicts[] = [
                    'type' => 'property_has_cleaner',
                    'message' => ($properties[$propertyId] ?? 'This property')." already has a clean on {$day} assigned to "
                        .($users[$other->housekeeper_id] ?? 'another cleaner').' ('.$fmt($other->scheduled_time).').',
                ];
            }
        }

        $stays = Booking::query()
            ->where('property_id', $propertyId)
            ->whereNull('cancelled_at')
            ->notArchived()
            ->where('check_in_date', '<', $date)
            ->where('check_out_date', '>', $date)
            ->get();

        foreach ($stays as $stay) {
            $conflicts[] = [
                'type' => 'property_occupied',
                'message' => ($stay->guest_name ?: 'A guest').' is staying at '.($properties[$propertyId] ?? 'this property').' '
                    .$stay->check_in_date->format('M j').' - '.$stay->check_out_date->format('M j').", so it is occupied on {$day}.",
            ];
        }

        return $conflicts;
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
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when($session, fn ($query) => $query->where('id', '<>', $session->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'scheduled_date' => 'Duplicate assignment for this housekeeper/property/date.',
            ]);
        }
    }
}
