<?php

namespace App\Http\Controllers;

use App\Models\PrivacyRequest;
use App\Models\Setting;
use App\Services\SmsNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PrivacyRequestController extends Controller
{
    public function index()
    {
        return view('public.privacy-request', [
            'siteLogo' => Setting::getValue('site_logo'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'request_type' => ['required', 'in:access,correction,deletion,portable_copy,opt_out,appeal'],
            'details' => ['nullable', 'string', 'max:4000'],
        ]);

        $data['status'] = 'new';
        $data['ip_address'] = $request->ip();

        $privacyRequest = PrivacyRequest::create($data);

        $adminEmail = Setting::getValue('contact_email', 'needhelp@guesthub.us');
        if ($adminEmail) {
            $phoneDisplay = $privacyRequest->phone ?? 'Not provided';

            // The request is already saved to the DB above regardless of
            // whether this notification email succeeds. A transient SMTP
            // failure should never turn into a 500 for the guest, or block
            // them submitting a privacy/deletion request -- log and move
            // on, same pattern as GuestAlertService's mail sends.
            try {
                $tpl = \App\Services\DirectBookingMessages::renderGeneric('staff_privacy_request', [
                    'sender_name' => $privacyRequest->name,
                    'sender_email' => $privacyRequest->email,
                    'sender_phone' => $phoneDisplay,
                    'request_type' => $privacyRequest->request_type,
                    'details' => $privacyRequest->details,
                ]);
                if ($tpl) {
                    Mail::raw($tpl['message'], function ($message) use ($adminEmail, $tpl) {
                        $message->to($adminEmail)->subject($tpl['subject']);
                    });
                }
            } catch (\Throwable $e) {
                Log::error('Privacy request admin notification email failed: '.$e->getMessage());
            }
        }

        $adminPhone = config('services.telnyx.admin_notify_number');
        if ($adminPhone) {
            SmsNotificationService::guestAlert(
                $adminPhone,
                "GuestHub privacy request: {$privacyRequest->name} ({$privacyRequest->email})\n"
                ."Type: {$privacyRequest->request_type}\n"
                .'Details: '.($privacyRequest->details ?: 'Not provided'),
                false
            );
        }

        return redirect()->route('privacy-request')->with('success', 'Your privacy request has been received. We will review it and respond as soon as possible.');
    }
}
