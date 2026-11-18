<?php

namespace App\\Console\\Commands;

use App\\Services\\Pms\\PriceLabs\\PriceLabsIapiClient;
use Illuminate\\Console\\Command;
use Throwable;

class PriceLabsRegisterIntegration extends Command
{
    protected $signature = 'pricelabs:register-integration';

    protected $description = 'Register GuestHub PriceLabs IAPI callback URLs and supported features.';

    public function handle(PriceLabsIapiClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('Configure PRICELABS_INTEGRATION_NAME, PRICELABS_INTEGRATION_TOKEN, and PRICELABS_IAPI_BASE_URL first.');

            return self::FAILURE;
        }

        try {
            $response = $client->integration('POST', [
                'integration' => [
                    'sync_url' => route('webhooks.pricelabs.sync'),
                    'calendar_trigger_url' => route('webhooks.pricelabs.calendar-trigger'),
                    'hook_url' => route('webhooks.pricelabs.hook'),
                    'regenerate_token' => false,
                    // GuestHub currently consumes nightly prices and minimum stay only.
                    'features' => [
                        'min_stay' => true,
                        'check_in' => false,
                        'check_out' => false,
                        'monthly_weekly_discounts' => false,
                        'extra_person_fee' => false,
                        'los_pricing' => false,
                        'delta_only' => false,
                    ],
                ],
            ]);

            if (! empty($response['error'])) {
                $this->error('PriceLabs rejected integration registration: '.(string) $response['error']);

                return self::FAILURE;
            }

            $this->info('PriceLabs integration callback URLs registered.');
            if (! empty($response['message'])) {
                $this->line((string) $response['message']);
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('PriceLabs integration registration failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
