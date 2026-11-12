<?php

namespace App\Http\Controllers;

use App\Mail\SignupAdminNotificationMail;
use App\Mail\SignupConfirmationMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SignupController extends Controller
{
    public function show()
    {
        return view('signup');
    }

    public function store(Request $request)
    {
        // Honeypot — bots fill this hidden field, humans don't
        if (filled($request->input('website'))) {
            return back()->with('success', "Thanks! We'll be in touch.");
        }

        // Rate limit: 3 attempts per IP per 15 minutes
        $key = 'signup:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors(['email' => "Too many signup attempts. Please wait {$seconds} seconds and try again."])->onlyInput('email', 'name', 'phone_number', 'requested_role');
        }
        RateLimiter::hit($key, 900);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone_number'   => ['required', 'string', 'max:30'],
            'requested_role' => ['required', 'in:owner,housekeeper,company'],
        ]);

        $user = User::create([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'phone_number'    => $data['phone_number'] ?? null,
            'password'        => Hash::make(Str::random(40)), // unusable until the approval link sets a real one
            'approval_status' => 'pending',
            'requested_role'  => $data['requested_role'],
            'is_active'       => false,
        ]);

        $adminEmail = Setting::getValue('contact_email');
        if ($adminEmail) {
            Mail::to($adminEmail)->send(new SignupAdminNotificationMail($user));
        }

        $adminPhone = config('services.telnyx.admin_notify_number');
        if ($adminPhone) {
            SmsNotificationService::guestAlert(
                $adminPhone,
                "New signup request on " . \App\Support\Branding::siteName() . ":\n"
                . "Name: {$user->name}\nEmail: {$user->email}\nRole: {$user->requested_role}",
                false
            );
        }

        Mail::to($user->email)->send(new SignupConfirmationMail($user));

        return redirect()->route('signup.pending');
    }

    public function pending()
    {
        return view('signup-pending');
    }
}
