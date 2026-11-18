<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItemPhoto;
use App\Models\CleaningSession;
use App\Models\RoomPhoto;
use Illuminate\Http\Request;

class PhotoRetainController extends Controller
{
    public function __invoke(Request $request, CleaningSession $session, string $type, int $id)
    {
        $photo = match ($type) {
            'room' => RoomPhoto::where('session_id', $session->id)->findOrFail($id),
            'task' => ChecklistItemPhoto::whereHas('checklistItem', fn ($q) => $q->where('session_id', $session->id))->findOrFail($id),
            default => abort(404),
        };

        abort_if($photo->purged_at !== null, 410, 'Photo already deleted');

        $photo->forceFill(['retained' => ! $photo->retained])->save();

        if ($request->expectsJson()) {
            return response()->json(['retained' => (bool) $photo->retained]);
        }
        return back();
    }
}
