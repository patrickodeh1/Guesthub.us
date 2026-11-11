<?php

namespace App\Mail;

use App\Helpers\TimezoneHelper;
use App\Models\CleaningSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $propertyName;
    public string $cleanerName;
    public string $startTime;
    public int $sessionId;
    public string $intro = '';
    public string $mailSubject = '';

    public function __construct(public CleaningSession $session)
    {
        $session->loadMissing(['property', 'housekeeper']);

        $this->propertyName = $session->property->name ?? 'Unknown Property';
        $this->cleanerName = $session->housekeeper->name ?? 'Unassigned';

        $tz = $session->property->timezone ?? config('app.timezone');
        $this->startTime = TimezoneHelper::format(
            $session->started_at ?? now(),
            $tz,
            TimezoneHelper::DATETIME
        );
        $this->sessionId = $session->id;

        $d = \App\Services\DirectBookingMessages::EVENTS['staff_session_started'];
        $tpl = \App\Services\DirectBookingMessages::renderGeneric('staff_session_started', [
            'property_name' => $this->propertyName, 'cleaner_name' => $this->cleanerName,
            'start_time' => $this->startTime, 'session_id' => $this->sessionId,
        ]) ?? ['subject' => $d['subject'], 'message' => $d['message']];
        $this->mailSubject = $tpl['subject'];
        $this->intro = $tpl['message'];
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.session-started',
        );
    }
}
