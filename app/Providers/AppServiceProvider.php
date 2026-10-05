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
        // Admin alert when a guest replaces an ID side that was already uploaded or declined (2nd+ attempt).
        \App\Models\Booking::updated(function (\App\Models\Booking $b) {
            foreach ([['photo_id_path', 'photo_id_front_declined_reason', 'front'], ['photo_id_back_path', 'photo_id_back_declined_reason', 'back']] as [$path, $reason, $side]) {
                if ($b->wasChanged($path) && filled($b->{$path}) && (filled($b->getOriginal($path)) || filled($b->getOriginal($reason)))) {
                    try {
                        \App\Services\GuestAlertService::send('photo_id_resubmitted', $b, ['id_side' => $side]);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('photo_id_resubmitted alert failed: '.$e->getMessage());
                    }
                    break;
                }
            }
        });

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
