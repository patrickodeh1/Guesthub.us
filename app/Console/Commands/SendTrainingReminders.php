<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CleaningSession;
use App\Services\CleaningSmsNotificationService;
use App\Services\TrainingValidationService;
use Carbon\Carbon;

class SendTrainingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'training:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send 24h and Same-Day reminders for pending mandatory training.';

    /**
     * Execute the console command.
     */
    public function handle(TrainingValidationService $validationService)
    {
        $now = Carbon::now();
        $tomorrow = Carbon::now()->addHours(24);
        
        $this->info("Starting training reminders check at {$now->toDateTimeString()}");

        // We only care about pending sessions that have a housekeeper assigned
        $query = CleaningSession::where('status', 'pending')
            ->whereNotNull('housekeeper_id')
            ->with(['housekeeper', 'property', 'assignmentTrainingSnapshots.task', 'assignmentTrainingSnapshots.video']);

        $count = $query->count();
        $this->info("Found {$count} upcoming pending sessions.");

        $query->chunkById(100, function ($sessions) use ($validationService) {
            foreach ($sessions as $session) {
                $user = $session->housekeeper;
                if (!$user || !$user->is_active) {
                    continue; // Skip deactivated cleaners
                }

                // Timezone evaluation
                // Attempt to get property timezone if available, otherwise fallback to app default
                $property = $session->property;
                $timezone = $property->timezone ?? config('app.timezone', 'UTC');
                $localNow = Carbon::now($timezone);
                $scheduledDate = Carbon::parse($session->scheduled_date, $timezone);

                // Determine if this is a 24h or Same Day reminder based on LOCAL time
                $timing = null;
                
                if ($scheduledDate->isSameDay($localNow)) {
                    $timing = 'sameday';
                } elseif ($scheduledDate->isSameDay($localNow->copy()->addDays(1))) {
                    // If it's scheduled for tomorrow local time, it is within ~24h.
                    // Or we could strictly check <= 25 hours.
                    if ($scheduledDate->diffInHours($localNow) <= 25) {
                        $timing = '24h';
                    }
                }

                if (!$timing) {
                    continue; // Skip if it is not due for reminder
                }

                // Get incomplete items
                $incompleteItems = $validationService->getIncompleteMandatoryItems($session, $user);
                $incompleteCount = count($incompleteItems);

                if ($incompleteCount === 0) {
                    continue; // Everything is completed, no reminder needed.
                }

                // Calculate estimated remaining duration
                $totalTimeMinutes = 0;
                foreach ($incompleteItems as $snapshot) {
                    if ($snapshot->task) {
                        $totalTimeMinutes += ($snapshot->task->estimated_duration_minutes ?? 1);
                    } elseif ($snapshot->video) {
                        $totalTimeMinutes += ceil(($snapshot->video->duration_seconds ?? 0) / 60);
                    }
                }

                try {
                    CleaningSmsNotificationService::sendTrainingReminder(
                        $session, 
                        $timing, 
                        $incompleteCount, 
                        (int)$totalTimeMinutes
                    );
                    $this->line("Triggered {$timing} reminder for Session {$session->id}.");
                } catch (\Exception $e) {
                    $this->error("Failed to send reminder for Session {$session->id}: {$e->getMessage()}");
                }
            }
        });

        $this->info('Training reminders check completed.');
    }
}
