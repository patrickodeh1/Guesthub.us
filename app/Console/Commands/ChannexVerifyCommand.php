<?php

namespace App\\Console\\Commands;

use App\\Services\\Pms\\ChannexProvider;
use Illuminate\\Console\\Command;

class ChannexVerifyCommand extends Command
{
    protected $signature = 'channex:verify';

    protected $description = 'Verify the configured Channex API key and show the selected environment';

    public function handle(ChannexProvider $channex): int
    {
        $environment = $channex->environment();
        $this->line('Channex environment: '.$environment);

        if (! config('services.channex.api_key')) {
            $this->error('CHANNEX_API_KEY is not configured.');

            return self::FAILURE;
        }

        $result = $channex->verifyConnection();

        if (! $result['ok']) {
            $this->error('Channex API verification failed (HTTP '.($result['status'] ?? 'no response').').');

            return self::FAILURE;
        }

        $this->info('Channex API key verified successfully.');

        return self::SUCCESS;
    }
}
