<?php

namespace App\Mail;

use App\Http\Controllers\PasswordSetupController;
use App\Models\User;
use App\Services\DirectBookingMessages;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SignupApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public bool $resend = false) {}

    public function build(): static
    {
        $setupUrl = PasswordSetupController::linkFor($this->user);
        $vars = ['{lead_name}' => $this->user->name, '{site_name}' => Branding::siteName()];

        if ($this->resend) {
            return $this->subject(strtr('Your new {site_name} password link', $vars))
                ->view('emails.signup-approved', [
                    'body' => strtr("Hi {lead_name},\n\nHere is a new link to create your password for {site_name}.", $vars),
                    'setupUrl' => $setupUrl,
                    'user' => $this->user,
                ]);
        }

        $cfg = DirectBookingMessages::config()['signup_approved'] ?? [];
        $body = strtr($cfg['message'] ?? "Hi {lead_name},\n\nGreat news — your account on {site_name} has been approved. Use the button below to create your password.", $vars);
        $subject = strtr($cfg['subject'] ?? 'Your {site_name} account is approved', $vars);

        return $this->subject($subject)
            ->view('emails.signup-approved', ['body' => $body, 'setupUrl' => $setupUrl, 'user' => $this->user]);
    }
}
