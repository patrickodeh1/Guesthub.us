<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SignupApprovedMail;
use App\Mail\SignupRejectedMail;
use App\Models\User;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        $message = "{$user->name} has been approved as " . ucfirst($data['role']) . '. ' . $this->deliverySummary($delivery);

        return back()->with('success', $message);
    }

    public function resendSetup(User $user)
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
        abort_unless($user->approval_status === 'approved' && $user->is_active, 422, 'Only active, approved users can receive temporary credentials.');

        $delivery = $this->sendTemporaryCredentials($user);

        return back()->with('success', 'A new temporary password was generated. ' . $this->deliverySummary($delivery));
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

        $email = ['status' => 'sent', 'error' => null];
        try {
            Mail::to($user->email)->send(new SignupApprovedMail($user, false, $temporaryPassword));
        } catch (\Throwable $e) {
            $email = ['status' => 'failed', 'error' => 'Email delivery failed.'];
            Log::error('Signup temporary-password email failed for user '.$user->id.': '.$e->getMessage());
        }

        $loginUrl = route('login');
        $message = 'Your Guest Hub account is approved. Sign in at ' . $loginUrl
            . ' using temporary password ' . $temporaryPassword
            . '. You must change it before accessing the account.';
        $sms = SmsNotificationService::deliver($user->phone_number, $message, 'account-approval');

        return ['email' => $email, 'sms' => $sms];
    }

    private function deliverySummary(array $delivery): string
    {
        $sent = [];
        $failed = [];

        foreach (['email' => 'email', 'sms' => 'text message'] as $key => $label) {
            if (($delivery[$key]['status'] ?? null) === 'sent') {
                $sent[] = $label;
            } else {
                $failed[] = $label;
            }
        }

        if ($sent && ! $failed) {
            return 'Temporary credentials sent by '.implode(' and ', $sent).'.';
        }

        if ($sent) {
            return 'Temporary credentials sent by '.implode(' and ', $sent).'; '.implode(' and ', $failed).' delivery failed. Check the mail/SMS configuration before resending.';
        }

        return 'Account approved, but both email and text delivery failed. Check the mail/SMS configuration before resending.';
    }

    public function reject(User $user)
    {
        $user->forceFill(['approval_status' => 'rejected'])->save();
        Mail::to($user->email)->send(new SignupRejectedMail($user));
        return back()->with('success', "{$user->name}'s signup request has been rejected.");
    }
}
