<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SignupApprovedMail;
use App\Mail\SignupRejectedMail;
use App\Models\User;
use App\Services\SmsNotificationService;
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

        $delivery = $this->sendTemporaryCredentials($user);

        $message = "{$user->name} has been approved as " . ucfirst($data['role']) . '. A temporary password was emailed';
        $message .= $delivery['status'] === 'sent' ? ' and texted.' : '. SMS could not be sent: ' . ($delivery['error'] ?? 'delivery failed');

        return back()->with('success', $message);
    }

    public function resendSetup(User $user)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        abort_unless($user->approval_status === 'approved' && $user->is_active, 422, 'Only active, approved users can receive temporary credentials.');

        $delivery = $this->sendTemporaryCredentials($user);

        $message = "A new temporary password was emailed to {$user->email}";
        $message .= $delivery['status'] === 'sent' ? ' and texted.' : '. SMS could not be sent: ' . ($delivery['error'] ?? 'delivery failed');

        return back()->with('success', $message);
    }

    private function sendTemporaryCredentials(User $user): array
    {
        // Eight numeric digits are easy to relay by SMS; the forced-change
        // middleware prevents access to the rest of the app until replaced.
        $temporaryPassword = (string) random_int(10000000, 99999999);

        $user->forceFill([
            'password' => $temporaryPassword,
            'must_change_password' => true,
        ])->save();

        Mail::to($user->email)->send(new SignupApprovedMail($user, false, $temporaryPassword));

        $loginUrl = route('login');
        $message = 'Your Guest Hub account is approved. Sign in at ' . $loginUrl
            . ' using temporary password ' . $temporaryPassword
            . '. You must change it before accessing the account.';

        return SmsNotificationService::deliver($user->phone_number, $message, 'account-approval');
    }

    public function reject(User $user)
    {
        $user->forceFill(['approval_status' => 'rejected'])->save();
        Mail::to($user->email)->send(new SignupRejectedMail($user));
        return back()->with('success', "{$user->name}'s signup request has been rejected.");
    }
}
