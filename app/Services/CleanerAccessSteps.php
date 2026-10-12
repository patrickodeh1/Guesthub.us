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

        if ($session->parking_needed === null) {
            return [];
        }

        $parking = (bool) $session->parking_needed;
        $property = $session->property;
        $preStart = self::isPreStart($session);

        return InstructionStep::where('property_id', $session->property_id)
            ->whereIn('type', $parking ? ['checkin', 'parking'] : ['checkin'])
            ->where('active', true)
            ->when($preStart, fn ($q) => $q->where('show_before_gps', true))
            ->where(fn ($q) => $parking
                ? $q->where('visibility', '!=', 'non_parkers_only')
                : $q->where('visibility', '!=', 'parkers_only'))
            ->where(fn ($q) => $q->whereNull('visibility')->orWhere('visibility', '!=', 'guests_only'))
            ->orderByRaw("case when type = 'checkin' then 0 else 1 end")
            ->orderBy('sort_order')
            ->with('images')
            ->get()
            ->map(fn ($s) => [
                'type'    => $s->type,
                'title'   => $s->title,
                'content' => self::render($s, $property, $preStart),
                'image'   => $s->imageUrl(),
                'images'  => $s->images->map(fn ($img) => $img->imageUrl())->values()->all(),
                ...self::lockFields($s, $session),
            ])
            ->values()
            ->all();
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

        return InstructionStep::where('property_id', $session->property_id)
            ->where('type', 'checkout')
            ->where(fn ($q) => $q->whereNull('visibility')->orWhereIn('visibility', ['all', 'cleaners_only']))
            ->where('active', true)
            ->orderBy('sort_order')
            ->with('images')
            ->get()
            ->map(fn ($s) => [
                'type'    => $s->type,
                'title'   => $s->title,
                'content' => $s->renderContentForProperty($property),
                'image'   => $s->imageUrl(),
                'images'  => $s->images->map(fn ($img) => $img->imageUrl())->values()->all(),
                ...self::lockFields($s, $session),
            ])
            ->values()
            ->all();
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

    /** Location counts as verified once the cleaner passed the check (or an admin granted a GPS override). */
    public static function locationVerified(CleaningSession $session): bool
    {
        return (bool) ($session->gps_confirmed_at || $session->gps_override_enabled);
    }
}
