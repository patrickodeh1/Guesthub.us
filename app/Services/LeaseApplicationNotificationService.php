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

    public function statusChanged(LeaseApplication $application, bool $firstApproval = true): void
    {
        if ($application->status === 'approved') {
            if ($firstApproval) {
                $this->approved($application);
            }

            return;
        }

        if ($application->status === 'declined') {
            $this->declined($application);

            return;
        }

        $this->sendTemplate('guest_lease_status', (string) $application->applicant_email,
            route('public.lease-application.fee', [$application->id, $application->token]), 'View application', $application);
    }

    /** Short heads-up only. The offer itself is sent later, once the host marks it ready. */
    public function approved(LeaseApplication $application): void
    {
        $url = route('public.lease-application.fee', [$application->id, $application->token]);
        $this->sendTemplate('guest_lease_approved', (string) $application->applicant_email, $url, 'View application', $application);
        $this->sendSms('guest_lease_approved_sms', $application);
    }

    public function declined(LeaseApplication $application): void
    {
        $extra = ['{reason}' => filled($application->decline_reason) ? 'Reason: '.trim((string) $application->decline_reason) : ''];
        $url = route('public.lease-application.fee', [$application->id, $application->token]);
        $this->sendTemplate('guest_lease_declined', (string) $application->applicant_email, $url, 'View application', $application, $extra);
        $this->sendSms('guest_lease_declined_sms', $application, $extra);
    }

    public function feePaid(LeaseApplication $application): void
    {
        $this->sendTemplate('guest_lease_fee_paid', (string) $application->applicant_email,
            route('public.lease-application.fee', [$application->id, $application->token]), 'View application', $application);
    }

    public function feePaymentReported(LeaseApplication $application): void
    {
        $owner = $application->property?->owner;
        if (! $owner || blank($owner->email)) {
            return;
        }

        $amount = '$'.number_format((int) $application->total_fee_cents / 100, 2);
        $this->send(
            $owner->email,
            'Lease application payment reported',
            "{$application->applicant_name} reported sending {$amount} for lease application #{$application->id} and uploaded a receipt. Check your account, then review the receipt and confirm the fee.",
            route('admin.lease-applications.show', $application),
            'Review payment',
            $application,
        );
    }

    /** Unused since the screening URL was removed; kept so the old admin route cannot fatal. */
    public function screeningRequested(LeaseApplication $application): void
    {
    }

    private function sendTemplate(string $key, string $recipient, string $url, string $action, LeaseApplication $application, array $extra = []): void
    {
        $m = DirectBookingMessages::renderLease($key, $application, $extra);
        if (! $m) {
            return;
        }
        $this->send($recipient, $this->tidy($m['subject']), $this->tidy($m['message']), $url, $action, $application);
    }

    /** Text the applicant. SmsNotificationService skips it unless they opted in. */
    private function sendSms(string $key, LeaseApplication $application, array $extra = []): void
    {
        try {
            $phone = trim((string) $application->applicant_phone);
            if ($phone === '') {
                return;
            }
            $m = DirectBookingMessages::renderLease($key, $application, $extra);
            if (! $m) {
                return;
            }
            SmsNotificationService::guestAlert($phone, $this->tidy($m['message']));
        } catch (Throwable $exception) {
            Log::warning('Lease application SMS failed.', [
                'application_id' => $application->id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function tidy(string $text): string
    {
        return trim((string) preg_replace('/[ \t]{2,}/', ' ', $text));
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
