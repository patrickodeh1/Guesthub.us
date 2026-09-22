<?php

namespace App\Http\Controllers;

use App\Mail\EarlyAccessAdminNotificationMail;
use App\Mail\EarlyAccessConfirmationMail;
use App\Models\EarlyAccessLead;
use App\Models\Setting;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EarlyAccessController extends Controller
{
    public function show()
    {
        return view('early-access', [
            'siteLogo' => Setting::getValue('site_logo'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['nullable', 'in:host,guest,other'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $lead = EarlyAccessLead::create($data);

        $adminEmail = Setting::getValue('contact_email');
        if ($adminEmail) {
            Mail::to($adminEmail)->send(new EarlyAccessAdminNotificationMail($lead));
        }

        $adminPhone = config('services.telnyx.admin_notify_number');
        if ($adminPhone) {
            SmsNotificationService::guestAlert(
                $adminPhone,
                "GuestHub early access: {$lead->name} ({$lead->email})\n"
                .'Phone: '.($lead->phone ?: 'Not provided')."\n"
                .'Role: '.($lead->role ?: 'Not provided')."\n"
                .'Message: '.($lead->message ?: 'Not provided'),
                false
            );
        }

        Mail::to($lead->email)->send(new EarlyAccessConfirmationMail($lead));

        return back()->with('success', "Thanks. We've received your info and will be in touch soon.");
    }
}
