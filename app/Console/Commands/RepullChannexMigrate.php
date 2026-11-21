<?php

namespace App\Console\Commands;

use App\Services\Pms\RepullChannexMigrationService;
use Illuminate\Console\Command;
use Throwable;

class RepullChannexMigrate extends Command
{
    protected $signature = 'repull:channex-migrate
        {--import : Perform the real import (without this flag the command is dry-run only)}
        {--cutover : Activate imported Repull webhooks after channel handover}
        {--environment=production : Channex environment to read (production or staging)}
        {--webhook-id=* : Limit cutover to the supplied imported webhook IDs}';

    protected $description = 'Dry-run/import Channex data into Repull or activate imported webhooks after channel migration.';

    public function handle(RepullChannexMigrationService $migration): int
    {
        if ($this->option('import') && $this->option('cutover')) {
            $this->error('Run import and cutover as separate steps.');
            return self::FAILURE;
        }

        try {
            if ($this->option('cutover')) {
                $result = $migration->cutover((array) $this->option('webhook-id'));
                $this->info('Repull Channex cutover response:');
            } else {
                $dryRun = ! $this->option('import');
                $result = $migration->import((string) $this->option('environment'), $dryRun);
                $this->info($dryRun ? 'Repull Channex migration dry-run response:' : 'Repull Channex import response:');
            }

            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
