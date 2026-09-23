<?php

namespace App\Http\Controllers;

use App\Models\CleanerInstructionFamiliarity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class UserFamiliarityController extends Controller
{
    /**
     * Reset familiarity for a single task.
     */
    public function resetTask(Request $request, User $user, Task $task)
    {
        $this->assertCanEdit($user);

        $familiarity = CleanerInstructionFamiliarity::where('user_id', $user->id)
            ->where('task_id', $task->id)
            ->first();

        if ($familiarity) {
            $oldValue = $familiarity->views_completed;
            $familiarity->update([
                'views_completed' => 0,
                'last_reset_at' => now(),
            ]);

            activity('users')
                ->performedOn($user)
                ->causedBy(auth()->user())
                ->withProperties([
                    'task_id' => $task->id,
                    'old_value' => $oldValue,
                    'new_value' => 0,
                ])
                ->log("Familiarity Reset");
        }

        return back()->with('success', "Familiarity reset for task: {$task->name}");
    }

    /**
     * Reset familiarity for all tasks for a cleaner.
     */
    public function resetAll(Request $request, User $user)
    {
        $this->assertCanEdit($user);

        CleanerInstructionFamiliarity::where('user_id', $user->id)->update([
            'views_completed' => 0,
            'last_reset_at' => now(),
        ]);

        activity('users')
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->log("Familiarity Reset All");

        return back()->with('success', "Familiarity reset for all tasks for this cleaner.");
    }

    /**
     * Ensure the authenticated user can edit the given cleaner.
     */
    private function assertCanEdit(User $user)
    {
        $authUser = auth()->user();

        if ($authUser->hasRole('admin')) {
            return;
        }

        if ($authUser->hasRole('company')) {
            $isDirectlyOwned = $user->owner_id === $authUser->id;
            $isIndirectlyOwned = User::where('id', $user->owner_id)
                ->where('owner_id', $authUser->id)
                ->exists();
            abort_unless($isDirectlyOwned || $isIndirectlyOwned, 403, 'Unauthorized.');
            return;
        }

        if ($authUser->hasRole('owner')) {
            abort_unless($user->owner_id === $authUser->id, 403, 'Unauthorized.');
            return;
        }

        abort(403, 'Unauthorized.');
    }
}
