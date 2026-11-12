<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;

class PasswordSetupController extends Controller
{
    /** Fresh 24h signed link. The "h" fingerprint changes once the password changes, so a link works only once. */
    public static function linkFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'account.password-setup',
            now()->addHours(24),
            ['user' => $user->id, 'h' => self::fingerprint($user)]
        );
    }

    private static function fingerprint(User $user): string
    {
        return substr(sha1((string) $user->password), 0, 16);
    }

    private function linkIsValid(Request $request, User $user): bool
    {
        return $request->hasValidSignature()
            && $user->approval_status === 'approved'
            && $user->is_active
            && hash_equals(self::fingerprint($user), (string) $request->query('h'));
    }

    public function show(Request $request, User $user)
    {
        return view('password-setup', [
            'valid' => $this->linkIsValid($request, $user),
            'user' => $user,
            'contactEmail' => Setting::getValue('contact_email'),
        ]);
    }

    public function store(Request $request, User $user)
    {
        if (! $this->linkIsValid($request, $user)) {
            return redirect($request->fullUrl());
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        return redirect()->route('login')
            ->with('status', 'Password created. You can now log in.')
            ->with('success', 'Password created. You can now log in.');
    }
}
