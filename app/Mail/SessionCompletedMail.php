<?php

namespace App\Mail;

use App\Helpers\TimezoneHelper;
use App\Models\CleaningSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SessionCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $propertyName;
    public string $cleanerName;
    public string $completionTime;
    public string $reportUrl;
    public int $sessionId;
    public string $statusText;
    public string $intro = '';
    public string $mailSubject = '';

    public function __construct(public CleaningSession $session)
    {
        $session->loadMissing(['property', 'housekeeper']);

        $this->propertyName = $session->property->name ?? 'Unknown Property';
        $this->cleanerName = $session->housekeeper->name ?? 'Unassigned';

        $tz = $session->property->timezone ?? config('app.timezone');
        $this->completionTime = TimezoneHelper::format(
            $session->ended_at ?? now(),
            $tz,
            TimezoneHelper::DATETIME
        );
        $this->sessionId = $session->id;

        $session->ensureReportToken();
        $this->reportUrl = route('reports.sessions.show', ['token' => $session->report_token]);

        // Determine status text from ChecklistReport data
        $hasUnresolved = \App\Models\ChecklistReport::where('session_id', $session->id)
            ->whereIn('type', ['damage', 'maintenance', 'supplies', 'safety'])
            ->where('status', '!=', 'resolved')
            ->exists();

        $this->statusText = $hasUnresolved ? 'Ready with Exceptions' : 'Ready';

        $d = \App\Services\DirectBookingMessages::EVENTS['staff_session_completed'];
        $tpl = \App\Services\DirectBookingMessages::renderGeneric('staff_session_completed', [
            'property_name' => $this->propertyName, 'cleaner_name' => $this->cleanerName,
            'completion_time' => $this->completionTime, 'status_text' => $this->statusText, 'session_id' => $this->sessionId,
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
            view: 'emails.session-completed',
        );
    }
}
