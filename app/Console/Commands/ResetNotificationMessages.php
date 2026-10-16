<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\GuestAlertService;
use Illuminate\Console\Command;

class ResetNotificationMessages extends Command
{
    protected $signature = 'notifications:reset-messages
        {--event=* : Only reset these event keys (default: all)}
        {--toggles : ALSO reset send toggles to code defaults (careful: defaults turn guest/contact/admin/owner ON)}
        {--dry-run : Show what would change without saving}';

    protected $description = 'Clear saved notification wording so Settings > Notifications shows the code defaults';

    public function handle(): int
    {
        $stored = json_decode(Setting::getValue('guest_alerts_config', '') ?: '', true) ?: [];
        $original = $stored;
        $only = array_filter((array) $this->option('event'));

        $unknown = array_diff($only, array_keys(GuestAlertService::EVENTS));
        if ($unknown) {
            $this->error('Unknown event(s): '.implode(', ', $unknown));
            return self::FAILURE;
        }

        $changed = 0;
        foreach (array_keys($stored) as $key) {
            if ($only && ! in_array($key, $only, true)) {
                continue;
            }

            if ($this->option('toggles')) {
                unset($stored[$key]);
                $changed++;
                $this->line("  {$key}: wording + toggles reset");
                continue;
            }

            $had = isset($stored[$key]['guest_message']) || isset($stored[$key]['staff_message']) || isset($stored[$key]['message']);
            unset($stored[$key]['guest_message'], $stored[$key]['staff_message'], $stored[$key]['message']);
            if ($had) {
                $changed++;
                $this->line("  {$key}: wording reset");
            }
        }

        if ($this->option('dry-run')) {
            $this->warn("Dry run: {$changed} event(s) would change. Nothing saved.");
            return self::SUCCESS;
        }

        $backup = storage_path('app/guest_alerts_config_backup_'.now()->format('Ymd_His').'.json');
        file_put_contents($backup, json_encode($original, JSON_PRETTY_PRINT));
        $this->info("Backup written: {$backup}");

        Setting::putValue('guest_alerts_config', json_encode($stored));
        $this->info("Reset {$changed} event(s). Run: php artisan cache:clear");

        return self::SUCCESS;
    }
}
