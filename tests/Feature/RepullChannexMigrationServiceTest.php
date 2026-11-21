<?php

namespace Tests\Feature;

use App\Services\Pms\RepullChannexMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RepullChannexMigrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_defaults_to_dry_run_and_uses_bearer_repul_key(): void
    {
        config([
            'services.repull.api_key' => 'repull-secret',
            'services.repull.base_url' => 'https://api.repull.dev',
            'services.channex.api_key' => 'channex-secret',
        ]);
        Http::fake([
            'api.repull.dev/v1/migrations/channex' => Http::response([
                'data' => ['attributes' => ['dry_run' => true, 'complete' => true]],
            ]),
        ]);

        $result = app(RepullChannexMigrationService::class)->import('production', true);

        $this->assertTrue(data_get($result, 'data.attributes.dry_run'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.repull.dev/v1/migrations/channex'
            && $request->hasHeader('Authorization', 'Bearer repull-secret')
            && $request['apiKey'] === 'channex-secret'
            && $request['dryRun'] === true);
    }

    public function test_cutover_uses_channex_compatible_base_url_and_user_api_key(): void
    {
        config([
            'services.repull.api_key' => 'repull-secret',
            'services.repull.channex_base_url' => 'https://api.repull.dev/channex/api/v1',
        ]);
        Http::fake([
            'api.repull.dev/channex/api/v1/migration/cutover' => Http::response([
                'data' => ['attributes' => ['done' => false]],
            ]),
        ]);

        $result = app(RepullChannexMigrationService::class)->cutover(['webhook-1']);

        $this->assertFalse(data_get($result, 'data.attributes.done'));
        Http::assertSent(fn ($request) => $request->url() === 'https://api.repull.dev/channex/api/v1/migration/cutover'
            && $request->hasHeader('user-api-key', 'repull-secret')
            && $request['webhook_ids'] === ['webhook-1']);
    }
}
