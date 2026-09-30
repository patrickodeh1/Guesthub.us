<?php

namespace App\Providers;

use App\Models\CleaningSession;
use App\Models\InstructionalVideo;
use App\Models\Property;
use App\Services\Pms\ChannexProvider;
use App\Services\Pms\NextPaxProvider;
use App\Services\Pms\PmsProviderInterface;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(CleaningSession::class, \App\Policies\CleaningSessionPolicy::class);
        Gate::policy(InstructionalVideo::class, \App\Policies\InstructionalVideoPolicy::class);
        Gate::policy(Property::class, \App\Policies\PropertyPolicy::class);

        if (request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }

        view()->composer('*', function ($view) {
            $view->with('siteName', \App\Support\Branding::siteName());
        });
    }
}
