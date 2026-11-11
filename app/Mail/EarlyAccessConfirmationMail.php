<?php

namespace App\Mail;

use App\Models\EarlyAccessLead;
use App\Services\DirectBookingMessages;
use App\Support\Branding;
use App\Support\PhoneFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EarlyAccessConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EarlyAccessLead $lead,
    ) {}

    public function build()
    {
        $vars = [
            'lead_name' => $this->lead->name,
            'lead_email' => $this->lead->email,
            'lead_phone' => $this->lead->phone ? PhoneFormatter::format($this->lead->phone) : 'Not provided',
            'lead_role' => $this->lead->role ? ucfirst($this->lead->role) : 'Not provided',
            'lead_message' => $this->lead->message ?: 'None',
            'site_name' => Branding::siteName(),
        ];
        $tpl = DirectBookingMessages::renderGeneric('guest_early_access', $vars)
            ?? ['subject' => DirectBookingMessages::EVENTS['guest_early_access']['subject'], 'message' => DirectBookingMessages::EVENTS['guest_early_access']['message']];

        return $this->subject($tpl['subject'])
            ->markdown('emails.early-access-confirmation')
            ->with(['lead' => $this->lead, 'body' => $tpl['message']]);
    }
}
