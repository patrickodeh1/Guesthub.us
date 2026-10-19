<?php

namespace App\Services;

use App\Models\CleaningSession;
use App\Models\InstructionStep;

class CleanerAccessSteps
{
    /**
     * Property access + parking steps for the cleaner on this session.
     * Returns [] until the cleaner has answered the parking question.
     * Same visibility rules as guests, plus 'cleaners_only' is always included.
     */
    public static function forSession(CleaningSession $session): array
    {
        // Access ends when the assignment is done (admins keep the preview).
        if (in_array($session->status, ['completed', 'cancelled'], true) && ! auth()->user()?->hasRole('admin')) {
            return [];
        }

        // gh-no-parking-for-cleaners: cleaners park in the staff area, so they
        // always get the same steps as guests without parking.
        $parking = false;
        $property = $session->property;
        $preStart = self::isPreStart($session);
        $hidePreGps = ! $preStart
            && auth()->check() && (int) $session->housekeeper_id === (int) auth()->id()
            && (self::locationVerified($session) || $session->status === 'in_progress')
            && \Illuminate\Support\Facades\Schema::hasColumn('instruction_steps', 'show_before_gps');
        // Before verification the assigned cleaner sees only the "show before GPS" steps (filtered below).

        $steps = InstructionStep::where('property_id', $session->property_id)
            ->whereIn('type', $parking ? ['checkin', 'parking'] : ['checkin'])
            ->where('active', true)
            ->when($preStart, fn ($q) => $q->where('show_before_gps', true))
            ->when($hidePreGps, fn ($q) => $q->where(fn ($w) => $w->where('show_before_gps', false)->orWhere('action', 'door_lock')))
            ->where('for_cleaner', true)
            ->orderByRaw("case when type = 'checkin' then 0 else 1 end")
            ->orderBy('sort_order')
            ->with('images')
            ->get()
            ->map(fn ($s) => [
                'step_id' => $s->id,
                'type'    => $s->type,
                'title'   => $s->title,
                'content' => self::render($s, $property, $preStart),
                'image'   => $s->imageUrl(),
                'images'  => $s->images->map(fn ($img) => $img->imageUrl())->values()->all(),
                ...self::lockFields($s, $session),
                ...self::photoFields($s, $session),
            ])
            ->values()
            ->all();

        return self::withPreEntryTasks($steps, $session);
    }

    /**
     * Steps for leaving the unit: type 'checkout' shown to cleaners only or to both guests and cleaners.
     * Guest-only checkout steps never reach cleaners.
     * Cleaners get them only while the session is in progress; none after completion.
     */
    public static function exitForSession(CleaningSession $session): array
    {
        $isAdmin = (bool) auth()->user()?->hasRole('admin');
        if (in_array($session->status, ['completed', 'cancelled'], true)) {
            return [];
        }
        if (! $isAdmin && $session->status !== 'in_progress') {
            return [];
        }

        $property = $session->property;

        $steps = InstructionStep::where('property_id', $session->property_id)
            ->where('type', 'checkout')
            ->where('for_cleaner', true)
            ->where('active', true)
            ->orderBy('sort_order')
            ->with('images')
            ->get()
            ->map(fn ($s) => [
                'step_id' => $s->id,
                'type'    => $s->type,
                'title'   => $s->title,
                'content' => $s->renderContentForProperty($property),
                'image'   => $s->imageUrl(),
                'images'  => $s->images->map(fn ($img) => $img->imageUrl())->values()->all(),
                ...self::lockFields($s, $session),
                ...self::photoFields($s, $session),
            ])
            ->values()
            ->all();

        return self::withPreEntryTasks($steps, $session, 'checkout');
    }

    /** Extra tasks (phase during_cleaning) shown as wizard steps right after the step chosen for each. $kind is 'checkin' (access steps) or 'checkout' (exit steps). */
    private static function withPreEntryTasks(array $steps, CleaningSession $session, string $kind = 'checkin'): array
    {
        $tasks = $session->property->propertyTasks()
            ->where('tasks.phase', 'during_cleaning')
            ->wherePivot('visible_to_housekeeper', true)
            ->get();
        if ($tasks->isEmpty()) {
            return $steps;
        }

        $ids = fn (string $type) => InstructionStep::where('property_id', $session->property_id)
            ->where('type', $type)->where('for_cleaner', true)->where('active', true)
            ->pluck('id')->map(fn ($i) => (int) $i)->all();
        $checkinIds = $ids('checkin');
        $checkoutIds = $ids('checkout');
        $doneIds = \App\Models\ChecklistItem::where('session_id', $session->id)
            ->whereNull('room_id')->where('checked', true)
            ->pluck('task_id')->map(fn ($i) => (int) $i)->all();
        $visibleIds = array_map('intval', array_column($steps, 'step_id'));
        $hiddenIds = [];
        if ($kind === 'checkin' && \Illuminate\Support\Facades\Schema::hasColumn('instruction_steps', 'show_before_gps')
            && auth()->check() && (int) $session->housekeeper_id === (int) auth()->id()
            && (self::locationVerified($session) || $session->status === 'in_progress')) {
            $hiddenIds = InstructionStep::where('property_id', $session->property_id)
                ->where('show_before_gps', true)->where(fn ($q) => $q->whereNull('action')->orWhere('action', '!=', 'door_lock'))
                ->pluck('id')->map(fn ($i) => (int) $i)->all();
        }

        $atStart = [];
        $atEnd = [];
        $after = [];
        foreach ($tasks as $t) {
            $afterId = (int) ($t->pivot->show_after_step_id ?? 0);
            $isExit = $afterId === -1 || in_array($afterId, $checkoutIds, true);
            if ($isExit !== ($kind === 'checkout')) {
                continue;
            }
            $text = trim((string) ($t->pivot->instructions ?: $t->instructions));
            $entry = [
                'step_id'   => null,
                'type'      => $kind,
                'title'     => $t->name,
                'content'   => $text !== '' ? nl2br(e($text)) : '',
                'image'     => null,
                'images'    => [],
                'action'    => 'content',
                'task_url'  => route('checklist.property-task.toggle', [$session, $t]),
                'task_done' => in_array((int) $t->id, $doneIds, true),
                'lockbox_url' => $t->type === 'lockbox' ? route('sessions.lockbox', $session) : null,
                'lockbox_key' => 'lockbox_saved_' . $session->id,
            ];
            if ($afterId > 0 && in_array($afterId, $visibleIds, true)) {
                $after[$afterId][] = $entry;
            } elseif ($isExit) {
                $atEnd[] = $entry;
            } elseif ($afterId > 0 && in_array($afterId, $checkinIds, true) && ! in_array($afterId, $hiddenIds, true)) {
                continue; // its step is not visible yet (before location check); show it later
            } else {
                $atStart[] = $entry;
            }
        }

        $out = $atStart;
        foreach ($steps as $st) {
            $out[] = $st;
            foreach ($after[(int) ($st['step_id'] ?? 0)] ?? [] as $e) {
                $out[] = $e;
            }
        }
        return array_merge($out, $atEnd);
    }

    /** The assigned cleaner sees only "show before GPS" steps until the session is started at the property. */
    private static function isPreStart(CleaningSession $session): bool
    {
        if ($session->status !== 'pending') {
            return false;
        }
        if (! auth()->check() || (int) $session->housekeeper_id !== (int) auth()->id()) {
            return false;
        }
        if (! \Illuminate\Support\Facades\Schema::hasColumn('instruction_steps', 'show_before_gps')) {
            return false;
        }
        return ! self::locationVerified($session);
    }

    private static function render(InstructionStep $s, $property, bool $preStart): string
    {
        if ($preStart) {
            $s->content = str_replace('[[lockbox_code]]', '(shown once you arrive and start)', (string) $s->content);
        }
        return $s->renderContentForProperty($property);
    }

    /** Door-lock steps get a working lock control for the assigned cleaner once they are at the property. */
    private static function lockFields(InstructionStep $s, CleaningSession $session): array
    {
        $plain = ['action' => 'content'];
        if (($s->action ?? 'content') !== 'door_lock') {
            return $plain;
        }
        $isCleaner = auth()->check() && (int) $session->housekeeper_id === (int) auth()->id();
        $open = $session->status === 'in_progress'
            || ($session->status === 'pending' && self::locationVerified($session));
        if (! $isCleaner || ! $open) {
            return $plain;
        }
        $lock = $session->property->locks()->first();
        if (! $lock) {
            return ['action' => 'door_lock'];
        }
        try {
            $status = app(SeamService::class)->getLockStatus($lock->seam_device_id);
        } catch (\Throwable $e) {
            $status = $lock->last_known_locked;
        }

        return [
            'action' => 'door_lock',
            'lock_id' => $lock->id,
            'lock_status' => $status,
            'unlock_url' => route('sessions.door.unlock', [$session, $lock]),
            'lock_url' => route('sessions.door.lock', [$session, $lock]),
            'status_url' => route('sessions.door.status', [$session, $lock]),
        ];
    }

    /** Steps flagged "photo required" get an upload control for the assigned cleaner only. */
    private static function photoFields(InstructionStep $s, CleaningSession $session): array
    {
        if (! $s->requires_photo) {
            return [];
        }
        if (! auth()->check() || (int) $session->housekeeper_id !== (int) auth()->id()) {
            return [];
        }

        return [
            'photo_url' => route('sessions.access-photo', [$session, $s->id]),
            'photo_done' => \App\Models\SessionAccessPhoto::where('cleaning_session_id', $session->id)
                ->where('instruction_step_id', $s->id)->exists(),
        ];
    }

    /** Location counts as verified once the cleaner passed the check (or an admin granted a GPS override). */
    public static function locationVerified(CleaningSession $session): bool
    {
        return (bool) ($session->gps_confirmed_at || $session->gps_override_enabled);
    }
}
