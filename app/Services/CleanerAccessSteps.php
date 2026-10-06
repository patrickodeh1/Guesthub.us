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
        if ($session->parking_needed === null) {
            return [];
        }

        $parking = (bool) $session->parking_needed;
        $property = $session->property;

        return InstructionStep::where('property_id', $session->property_id)
            ->whereIn('type', $parking ? ['checkin', 'parking'] : ['checkin'])
            ->where('active', true)
            ->where(fn ($q) => $parking
                ? $q->where('visibility', '!=', 'non_parkers_only')
                : $q->where('visibility', '!=', 'parkers_only'))
            ->orderByRaw("case when type = 'checkin' then 0 else 1 end")
            ->orderBy('sort_order')
            ->with('images')
            ->get()
            ->map(fn ($s) => [
                'type'    => $s->type,
                'title'   => $s->title,
                'content' => $s->renderContentForProperty($property),
                'image'   => $s->imageUrl(),
                'images'  => $s->images->map(fn ($img) => $img->imageUrl())->values()->all(),
                'action'  => 'content', // cleaners get door-lock steps as plain instructions (no guest unlock button)
            ])
            ->values()
            ->all();
    }
}
