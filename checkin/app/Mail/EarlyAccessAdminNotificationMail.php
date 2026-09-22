<?php

namespace App\Mail;

use App\Models\EarlyAccessLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EarlyAccessAdminNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EarlyAccessLead $lead,
    ) {}

    public function build()
    {
        return $this->subject('New early access signup: ' . $this->lead->name)
            ->markdown('emails.early-access-admin-notification')
            ->with([
                'lead' => $this->lead,
            ]);
    }
}
