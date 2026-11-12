<?php

namespace App\Mail;

use App\Models\User;
use App\Services\DirectBookingMessages;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SignupRejectedMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public User $user) {}

    public function build(): static
    {
        $cfg = DirectBookingMessages::config()['signup_rejected'] ?? [];
        $replacements = [
            '{lead_name}' => $this->user->name,
            '{site_name}' => Branding::siteName(),
        ];
        $body = strtr($cfg['message'] ?? "Hi {lead_name},

Thank you for your interest in {site_name}. After reviewing your request, we're unable to approve your account at this time.

Thanks,
{site_name}", $replacements);
        $subject = strtr($cfg['subject'] ?? "Update on your {site_name} signup request", $replacements);
        $view = 'signup-rejected' === 'signup-approved' ? 'emails.signup-approved' : 'emails.signup-rejected';
        return $this->subject($subject)
            ->view($view, ['body' => $body, 'user' => $this->user]);
    }
}
