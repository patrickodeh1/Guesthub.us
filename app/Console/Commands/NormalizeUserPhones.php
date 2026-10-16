<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NormalizeUserPhones extends Command
{
    protected $signature = 'phones:normalize {--apply : Write the changes (default is a dry run)}';
    protected $description = 'Convert stored user phone numbers that already have a + country code to +digits form; list the rest.';

    public function handle(): int
    {
        $manual = [];
        foreach (User::whereNotNull('phone_number')->where('phone_number', '<>', '')->get(['id', 'name', 'phone_number']) as $u) {
            $raw = trim($u->phone_number);
            if (preg_match('/^\+[1-9]\d{7,14}$/', $raw)) {
                continue;
            }
            $candidate = str_starts_with($raw, '+') ? '+'.preg_replace('/\D+/', '', $raw) : null;
            if ($candidate && preg_match('/^\+[1-9]\d{7,14}$/', $candidate)) {
                $this->line("{$u->id} {$u->name}: {$raw} -> {$candidate}");
                if ($this->option('apply')) {
                    DB::table('users')->where('id', $u->id)->update(['phone_number' => $candidate]);
                }
            } else {
                $manual[] = [$u->id, $u->name, $raw];
            }
        }
        if ($manual) {
            $this->warn('No country code, fix these by hand (profile page):');
            $this->table(['id', 'name', 'number'], $manual);
        }
        $this->info($this->option('apply') ? 'Applied.' : 'Dry run only. Re-run with --apply to save.');
        return self::SUCCESS;
    }
}
