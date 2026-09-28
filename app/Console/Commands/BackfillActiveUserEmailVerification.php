<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillActiveUserEmailVerification extends Command
{
    protected $signature = 'users:backfill-email-verification
                            {--dry-run : Show the eligible count without changing records}
                            {--apply : Apply the backfill; otherwise this command is preview-only}';

    protected $description = 'Set email verification timestamps for active users that do not have one';

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Choose either --dry-run or --apply, not both.');

            return self::INVALID;
        }

        $users = DB::table('users')
            ->where('status', 'active')
            ->whereNull('email_verified_at');
        $count = (clone $users)->count();

        $this->info("Active users missing email verification: {$count}.");

        if (! $this->option('apply')) {
            $this->comment('Preview only; no records were changed. Pass --apply to perform the backfill.');

            return self::SUCCESS;
        }

        $updated = $users->update([
            'email_verified_at' => now(),
            'updated_at' => now(),
        ]);

        $this->info("Backfill complete. Updated {$updated} active user(s).");

        return self::SUCCESS;
    }
}
