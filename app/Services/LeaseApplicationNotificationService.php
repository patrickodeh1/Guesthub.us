<?php

namespace App\Services;

use App\Mail\DirectBookingMail;
use App\Models\LeaseApplication;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class LeaseApplicationNotificationService
{
    public function submitted(LeaseApplication $application): void
    {
        $application->loadMissing(['property.owner', 'listing']);
        $url = route('public.lease-application.fee', [$application->id, $application->token]);
        $this->sendTemplate('guest_lease_received', (string) $application->applicant_email, $url, 'View application', $application);

        $owner = $application->property->owner;
        if ($owner && filled($owner->email)) {
            $this->sendTemplate('host_lease_new', $owner->email, route('admin.lease-applications.show', $application), 'Review application', $application);
        }
    }

    public function statusChanged(LeaseApplication $application): void
    {
        $this->sendTemplate('guest_lease_status', (string) $application->applicant_email,
            route('public.lease-application.fee', [$application->id, $application->token]), 'View application', $application);
    }

    public function feePaid(LeaseApplication $application): void
    {
        $this->sendTemplate('guest_lease_fee_paid', (string) $application->applicant_email,
            route('public.lease-application.fee', [$application->id, $application->token]), 'View application', $application);
    }

    /** Unused since the screening URL was removed; kept so the old admin route cannot fatal. */
    public function screeningRequested(LeaseApplication $application): void
    {
    }

    private function sendTemplate(string $key, string $recipient, string $url, string $action, LeaseApplication $application): void
    {
        $m = DirectBookingMessages::renderLease($key, $application);
        if (! $m) {
            return;
        }
        $this->send($recipient, $m['subject'], $m['message'], $url, $action, $application);
    }

    private function send(string $recipient, string $subject, string $message, string $url, string $action, LeaseApplication $application): void
    {
        if (blank($recipient)) {
            return;
        }

        try {
            Mail::to($recipient)->send(new DirectBookingMail($subject, $message, $url, $action));
        } catch (Throwable $exception) {
            Log::warning('Lease application notification failed.', [
                'application_id' => $application->id,
                'subject' => $subject,
                'exception' => $exception::class,
            ]);
        }
    }
}
