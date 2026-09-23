<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ForcePasswordChangeController extends Controller
{
    /**
     * Display the force password change view.
     */
    public function show(Request $request)
    {
        $user = $request->user();
        // If they don't need to change password, redirect to dashboard
        if (! $user->must_change_password && ! Hash::check('password', $user->password)) {
            return redirect()->route('dashboard');
        }

        return view('auth.force-password-change');
    }

    /**
     * Handle an incoming new password request.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user->must_change_password && ! Hash::check('password', $user->password)) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults(), function ($attribute, $value, $fail) {
                if ($value === 'password') {
                    $fail('Your new password cannot be the default "password".');
                }
            }],
        ]);

        $user->update([
            'password' => $request->password, // Mutator/cast handles hashing
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('success', 'Password successfully updated.');
    }
}
