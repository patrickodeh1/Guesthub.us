<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\CleaningSession;
use App\Policies\CleaningSessionPolicy;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(CleaningSession::class, CleaningSessionPolicy::class);
        Gate::policy(\App\Models\InstructionalVideo::class, \App\Policies\InstructionalVideoPolicy::class);
        Gate::policy(\App\Models\Property::class, \App\Policies\PropertyPolicy::class);

        if (str_starts_with(config('app.url') ?? '', 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Share site name with all views
        view()->composer('*', function ($view) {
            $view->with('siteName', \App\Models\Setting::get('site_name', config('app.name', 'HK Checklist')));
        });
    }
}

