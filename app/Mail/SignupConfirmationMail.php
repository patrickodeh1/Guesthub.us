<?php

namespace App\Mail;

use App\Models\User;
use App\Services\DirectBookingMessages;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SignupConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public User $user) {}

    public function build(): static
    {
        $cfg = DirectBookingMessages::config()['guest_early_access'] ?? [];
        $body = strtr($cfg['message'] ?? "Hi {lead_name},\n\nWe've received your signup request for {site_name}. We'll be in touch once your account is approved.", [
            '{lead_name}' => $this->user->name,
            '{site_name}' => Branding::siteName(),
        ]);
        return $this->subject($cfg['subject'] ?? 'Your signup request was received')
            ->view('emails.signup-confirmation', ['body' => $body, 'user' => $this->user]);
    }
}
