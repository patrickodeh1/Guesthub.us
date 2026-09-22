<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, true)) {
            // Always remember the user so a stale/backgrounded mobile tab
            // doesn't get logged out; the checkbox on the login form is
            // now cosmetic only.
            $request->session()->regenerate();

            /** @var User $user */
            $user = Auth::user();

            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            ActivityLogService::security('login', "{$user->name} logged in successfully.", [
                'actor_type'  => 'admin',
                'actor_id'    => $user->id,
                'actor_name'  => $user->name,
                'actor_email' => $user->email,
                'severity'    => 'info',
                'metadata'    => ['ip' => $request->ip()],
            ]);

            return redirect()->intended(route('admin.dashboard'))->with('success', 'Welcome back, '.$user->name.'.');
        }

        ActivityLogService::security('login_failed', "Failed login attempt for email: {$request->email}.", [
            'actor_type' => 'system',
            'severity'   => 'warning',
            'metadata'   => ['email' => $request->email, 'ip' => $request->ip()],
        ]);

        return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user) {
            ActivityLogService::security('logout', "{$user->name} logged out.", [
                'actor_type'  => 'admin',
                'actor_id'    => $user->id,
                'actor_name'  => $user->name,
                'actor_email' => $user->email,
                'severity'    => 'info',
            ]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
