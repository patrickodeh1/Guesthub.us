<?php

namespace App\Http\Controllers;

use App\Models\CleaningSession;
use App\Models\InstructionStep;
use App\Models\SessionAccessPhoto;
use Illuminate\Http\Request;

class SessionAccessPhotoController extends Controller
{
    public function store(Request $request, CleaningSession $session, InstructionStep $step)
    {
        $user = $request->user();
        abort_unless(
            (int) $session->housekeeper_id === (int) $user->id || $user->hasRole('admin'),
            403
        );
        abort_unless($step->requires_photo, 422, 'This step does not need a photo.');

        $request->validate(['photo' => ['required', 'image', 'max:15360']]);

        $path = $request->file('photo')->store('session-access-photos/'.$session->id, 'public');

        SessionAccessPhoto::updateOrCreate(
            ['cleaning_session_id' => $session->id, 'instruction_step_id' => $step->id],
            ['path' => $path, 'captured_at' => now()]
        );

        return response()->json(['ok' => true]);
    }
}
