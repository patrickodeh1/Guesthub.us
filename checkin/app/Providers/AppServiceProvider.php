<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Services\Pms\PmsProviderInterface;
use App\Services\Pms\ChannexProvider;
use App\Services\Pms\NextPaxProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Single place that decides which PMS provider implementation is
        // active — controlled by config/pms.php (PMS_PROVIDER env var).
        // Consuming code should only ever type-hint PmsProviderInterface.
        $this->app->bind(PmsProviderInterface::class, function () {
            return match (config('pms.provider')) {
                'nextpax' => new NextPaxProvider(),
                default => new ChannexProvider(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
