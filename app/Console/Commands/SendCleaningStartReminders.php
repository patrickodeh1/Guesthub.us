<?php

namespace App\Console\Commands;

use App\Models\CleaningSession;
use App\Services\CleaningSmsNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendCleaningStartReminders extends Command
{
    protected $signature = 'cleaning:send-start-reminders {--minutes=60 : Remind when the start is within this many minutes}';

    protected $description = 'Text confirmed cleaners that their cleaning starts in about an hour (once per job).';

    public function handle(): int
    {
        if (! config('services.sms.enabled')) {
            $this->info('SMS is turned off (SMS_ENABLED); nothing to send.');
            return self::SUCCESS;
        }

        $window = max(5, (int) $this->option('minutes'));
        $checked = 0;

        CleaningSession::with(['property', 'housekeeper'])
            ->where('status', 'pending')
            ->whereNull('started_at')
            ->whereNotNull('housekeeper_id')
            ->whereNotNull('scheduled_time')
            ->where('assignment_status', 'confirmed')
            ->whereDate('scheduled_date', '>=', now()->subDay()->toDateString())
            ->whereDate('scheduled_date', '<=', now()->addDay()->toDateString())
            ->chunkById(100, function ($sessions) use ($window, &$checked) {
                foreach ($sessions as $session) {
                    $user = $session->housekeeper;
                    if (! $user || ! $user->is_active || ! $session->property) {
                        continue;
                    }

                    $tz = $session->property->timezone ?? config('app.display_timezone');
                    $start = Carbon::parse(
                        $session->scheduled_date->format('Y-m-d').' '.$session->scheduled_time->format('H:i'),
                        $tz
                    );

                    $secs = $start->getTimestamp() - now()->getTimestamp();
                    $mins = intdiv($secs, 60);
                    if ($secs <= 0 || $mins > $window) {
                        continue;
                    }

                    CleaningSmsNotificationService::sendStartReminder($session, $mins);
                    $checked++;
                    $this->line("Start reminder handled for session {$session->id} ({$mins} min away).");
                }
            });

        $this->info("Done. {$checked} session(s) were in the reminder window.");
        return self::SUCCESS;
    }
}
