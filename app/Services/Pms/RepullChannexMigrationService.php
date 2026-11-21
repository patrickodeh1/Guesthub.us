<?php

namespace App\Services\Pms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class RepullChannexMigrationService
{
    public function import(string $environment = 'production', bool $dryRun = true): array
    {
        $repullKey = (string) config('services.repull.api_key');
        $channexKey = (string) config('services.channex.api_key');
        $baseUrl = rtrim((string) config('services.repull.base_url'), '/');

        if ($repullKey === '' || $channexKey === '') {
            throw new RuntimeException('Set REPULL_API_KEY and CHANNEX_API_KEY before running a Channex migration.');
        }
        if (! in_array($environment, ['production', 'staging'], true)) {
            throw new RuntimeException('Migration environment must be production or staging.');
        }

        $response = Http::withToken($repullKey)
            ->acceptJson()
            ->asJson()
            ->timeout(120)
            ->post($baseUrl.'/v1/migrations/channex', [
                'apiKey' => $channexKey,
                'environment' => $environment,
                'dryRun' => $dryRun,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Repull Channex migration failed with HTTP '.$response->status().'. Review the response in the application log.');
        }

        return $response->json() ?? [];
    }

    public function cutover(array $webhookIds = []): array
    {
        $repullKey = (string) config('services.repull.api_key');
        $baseUrl = rtrim((string) config('services.repull.channex_base_url'), '/');
        if ($repullKey === '') {
            throw new RuntimeException('Set REPULL_API_KEY before running migration cutover.');
        }

        $response = Http::baseUrl($baseUrl)
            ->withHeaders(['user-api-key' => $repullKey])
            ->acceptJson()
            ->asJson()
            ->timeout(120)
            ->post('/migration/cutover', $webhookIds !== [] ? ['webhook_ids' => array_values($webhookIds)] : []);

        if (! $response->successful()) {
            throw new RuntimeException('Repull Channex cutover failed with HTTP '.$response->status().'. Review the response in the application log.');
        }

        return $response->json() ?? [];
    }
}
