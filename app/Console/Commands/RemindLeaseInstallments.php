<?php

namespace App\Console\Commands;

use App\Models\LeaseInstallment;
use App\Mail\DirectBookingMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RemindLeaseInstallments extends Command
{
    protected $signature = 'lease-installments:remind';
    protected $description = 'Send one reminder for each upcoming or overdue unpaid lease installment.';

    public function handle(): int
    {
        $sent = 0;
        LeaseInstallment::query()->where('status', LeaseInstallment::STATUS_PENDING)
            ->whereNull('reminded_at')->whereDate('due_date', '<=', now()->addDays(3)->toDateString())
            ->with(['offer.application', 'offer.property'])
            ->chunkById(100, function ($installments) use (&$sent) {
                foreach ($installments as $item) {
                    $offer = $item->offer;
                    if (! $offer || ! in_array($offer->status, ['confirmed', 'signed'], true)) continue;
                    $application = $offer->application;
                    if (! $application?->applicant_email) continue;
                    try {
                        Mail::to($application->applicant_email)->send(new DirectBookingMail(
                            'Upcoming rental payment: '.$item->label,
                            'Your payment of $'.number_format($item->amount_cents / 100, 2).' for '.$item->label.' is due '.$item->due_date->format('M j, Y').'. Open your rental offer to review payment instructions.',
                            route('public.lease-offer.show', [$application->id, $application->token, $offer->token]),
                            'View rental offer',
                        ));
                        $item->update(['reminded_at' => now()]);
                        $sent++;
                    } catch (Throwable $e) { report($e); }
                }
            });
        $this->info("Sent {$sent} lease installment reminders.");
        return self::SUCCESS;
    }
}
