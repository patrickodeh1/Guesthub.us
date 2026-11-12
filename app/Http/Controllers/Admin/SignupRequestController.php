<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SignupApprovedMail;
use App\Mail\SignupRejectedMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SignupRequestController extends Controller
{
    public function index()
    {
        $pendingUsers = User::pending()->latest()->get();

        return view('admin.signup-requests.index', compact('pendingUsers'));
    }

    public function approve(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', 'in:' . implode(',', User::ROLES)],
        ]);

        $user->syncRoles([$data['role']]);
        $user->forceFill([
            'approval_status' => 'approved',
            'is_active'       => true,
        ])->save();

        Mail::to($user->email)->send(new SignupApprovedMail($user));

        return back()->with('success', "{$user->name} has been approved as " . ucfirst($data['role']) . '. A password link was emailed.');
    }

    public function resendSetup(User $user)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        abort_unless($user->approval_status === 'approved' && $user->is_active, 422, 'Only active, approved users can receive a password link.');

        Mail::to($user->email)->send(new SignupApprovedMail($user, true));

        return back()->with('success', "A new password link was sent to {$user->email}.");
    }

    public function reject(User $user)
    {
        $user->forceFill(['approval_status' => 'rejected'])->save();
        Mail::to($user->email)->send(new SignupRejectedMail($user));
        return back()->with('success', "{$user->name}'s signup request has been rejected.");
    }
}
