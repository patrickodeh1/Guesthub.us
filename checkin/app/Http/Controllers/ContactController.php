<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Setting;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function index()
    {
        return view('public.contact', [
            'siteLogo' => Setting::getValue('site_logo'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $contactMessage = ContactMessage::create([
            ...$data,
            'ip_address' => $request->ip(),
        ]);

        $notification = "Contact form submission\n\n"
            ."Name: {$contactMessage->name}\n"
            ."Email: {$contactMessage->email}\n"
            .'Phone: '.($contactMessage->phone ?: 'Not provided')."\n"
            ."Subject: {$contactMessage->subject}\n"
            ."Message:\n{$contactMessage->message}";

        $adminEmail = Setting::getValue('contact_email');
        if ($adminEmail) {
            try {
                Mail::raw($notification, function ($mail) use ($adminEmail, $contactMessage) {
                    $mail->to($adminEmail)
                        ->replyTo($contactMessage->email, $contactMessage->name)
                        ->subject('New contact form submission');
                });
            } catch (\Throwable $e) {
                Log::error('Contact form admin notification email failed: '.$e->getMessage());
            }
        }

        $adminPhone = config('services.telnyx.admin_notify_number');
        if ($adminPhone) {
            SmsNotificationService::guestAlert(
                $adminPhone,
                "GuestHub contact: {$contactMessage->name} ({$contactMessage->email})\n"
                ."Subject: {$contactMessage->subject}\n"
                ."Message: {$contactMessage->message}",
                false
            );
        }

        return redirect()->route('contact')->with('success', 'Thanks. Your message has been received and our team will be in touch.');
    }
}
