<?php

namespace App\Console\Commands;

use App\Services\GuestAlertService;
use Illuminate\Console\Command;

class ApplyClientNotificationDefaults extends Command
{
    protected $signature = 'notifications:apply-client-defaults {--dry-run : Show what would change without saving}';

    protected $description = 'Writes the client-approved notification matrix and wording into the saved Settings > Notifications config';

    public function handle(): int
    {
        $c = GuestAlertService::config();
        $sources = GuestAlertService::RECIPIENT_SOURCES;
        $staff = ['contact', 'admin', 'owner']; // "admin" audience = contact desk + admin + owner

        $timeMsg = 'Your reservation details have been updated. Check in {check_in_time} {check_in_date}. Check out {check_out_time} {check_out_date}.';

        // key => [guest on, admin on, guest message|null, admin message|null]
        $plan = [
            'registration_received'     => [1, 1, 'Thank you for your pre-check-in details. We will reach out soon.', '{guest_name} has completed pre check-in for {property_internal_name}. View: {admin_link}'],
            'background_check_complete' => [1, 0, "Your {step_name} for {property_name} is complete. Here's what needs to be done next: {guest_link}", null],
            'checkin_ready'             => [1, 0, 'Your unit at {property_name} is ready. Check in now: {guest_link}', null],
            'checkin_completed'         => [0, 1, null, '{guest_name} has checked into {property_internal_name}.'],
            'checkout_reminder'         => [1, 0, null, null],
            'checkout_completed'        => [1, 1, null, '{guest_name} has checked out of {property_internal_name}.'],
            'photo_id_declined'         => [1, 0, 'Hi {guest_first_name}, the {id_side} of your ID for {property_name} was not approved. Reason: {decline_reason}. Please resubmit here: {guest_link}', null],
            'photo_id_expired'          => [1, 0, 'Hi {guest_first_name}, the ID you uploaded for {property_name} appears to be expired. Please upload a current ID here: {guest_link}', null],
            'photo_id_resubmitted'      => [0, 1, null, null],
            'registration_reminder'     => [1, 0, null, null],
            'arrival_soon'              => [0, 1, null, null],
        ];
        foreach (['early_checkin_granted', 'late_checkout_granted', 'checkin_time_approved', 'checkin_time_denied', 'checkout_time_approved', 'checkout_time_denied'] as $k) {
            $plan[$k] = [1, 0, $timeMsg, null];
        }

        if (! $this->option('dry-run')) {
            $backup = storage_path('app/guest_alerts_config_backup_'.now()->format('Ymd_His').'.json');
            file_put_contents($backup, json_encode($c, JSON_PRETTY_PRINT));
            $this->info("Backup written: {$backup}");
        }

        // Everything off first, then switch on only what the client listed.
        foreach ($c as $k => $row) {
            foreach ($sources as $s) {
                $c[$k][$s.'_sms'] = false;
                $c[$k][$s.'_email'] = false;
            }
        }

        foreach ($plan as $k => [$g, $a, $gm, $sm]) {
            $c[$k] = $c[$k] ?? [];
            if ($gm) { $c[$k]['guest_message'] = $gm; }
            if ($sm) { $c[$k]['staff_message'] = $sm; }
            $c[$k]['guest_sms'] = $c[$k]['guest_email'] = (bool) $g;
            foreach ($staff as $s) {
                $c[$k][$s.'_sms'] = $c[$k][$s.'_email'] = (bool) $a;
            }
            $this->line(sprintf('  %-28s guest:%s admin:%s', $k, $g ? 'ON ' : 'off', $a ? 'ON ' : 'off'));
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run: nothing saved.');
            return self::SUCCESS;
        }

        GuestAlertService::putConfig($c);
        $this->info('Saved '.count($plan).' active events; every other event is off.');

        return self::SUCCESS;
    }
}
