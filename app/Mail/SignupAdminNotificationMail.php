<?php

namespace App\Mail;

use App\Models\User;
use App\Services\DirectBookingMessages;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SignupAdminNotificationMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public User $user) {}

    public function build(): static
    {
        $cfg = DirectBookingMessages::config()['staff_early_access'] ?? [];
        $body = strtr($cfg['message'] ?? "New signup request\n\nName: {lead_name}\nEmail: {lead_email}\nPhone: {lead_phone}\nRole: {lead_role}", [
            '{lead_name}'  => $this->user->name,
            '{lead_email}' => $this->user->email,
            '{lead_phone}' => $this->user->phone_number ?? 'Not provided',
            '{lead_role}'  => $this->user->requested_role ?? 'Not provided',
            '{lead_message}' => '',
            '{site_name}'  => Branding::siteName(),
        ]);
        $subject = strtr($cfg['subject'] ?? 'New signup: {lead_name}', ['{lead_name}' => $this->user->name]);
        return $this->subject($subject)
            ->view('emails.signup-admin-notification', ['body' => $body, 'user' => $this->user]);
    }
}
