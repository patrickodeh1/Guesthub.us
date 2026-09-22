<?php

namespace App\Services;

use Illuminate\Support\Collection;
use App\Models\CleaningSession;

class ReportItemFilter
{
    /**
     * An item is an ISSUE if it has:
     * - A non-empty note (whitespace-only does not count)
     * - OR at least one photo attached
     *
     * Instruction-only tasks are excluded from the issues list.
     * Unchecked status alone does NOT qualify as an issue.
     */
    public function getIssues(CleaningSession $session): Collection
    {
        return $session->checklistItems->filter(function ($item) {
            // Exclude instruction-only tasks from issues
            if ($item->isInstructionTask()) {
                return false;
            }

            if ($item->isVerificationItem()) {
                return $item->hasComment();
            }

            return $item->hasComment() || $item->hasImages();
        })->values();
    }

    /**
     * An item qualifies for the Compliance Checks section if and ONLY if ALL these conditions are true:
     * - Item has task->type == 'verify'
     */
    public function getComplianceItems(CleaningSession $session): Collection
    {
        return $session->checklistItems->filter(function ($item) {
            return $item->isVerificationItem();
        })->values();
    }

    /**
     * Returns all pictures from the session grouped by room_id.
     */
    public function getAllPicturesGroupedByRoom(CleaningSession $session): Collection
    {
        $allPictures = collect();

        foreach ($session->checklistItems as $item) {
            if ($item->hasImages()) {
                foreach ($item->photos as $photo) {
                    $allPictures->push([
                        'room_id' => $item->room_id,
                        'photo' => $photo,
                        'item' => $item, // Keep reference to item if needed
                    ]);
                }
            }
        }

        // We also need to get session level photos (room photos) that are not attached to a task
        if ($session->relationLoaded('photos')) {
            foreach ($session->photos as $photo) {
                $allPictures->push([
                    'room_id' => $photo->room_id,
                    'photo' => $photo,
                    'item' => null, // Session photos have no specific item
                ]);
            }
        }

        // Group by room_id
        return $allPictures->groupBy('room_id');
    }
}
