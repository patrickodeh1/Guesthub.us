<?php

return [

    // 'channex' | 'nextpax'. Everything else in the app depends only on
    // App\Services\Pms\PmsProviderInterface — this is the one line that
    // decides which concrete class gets bound to it (see
    // App\Providers\PmsServiceProvider).
    'provider' => env('PMS_PROVIDER', 'channex'),

    // Minutes between scheduled polls of the active provider's booking feed.
    'poll_interval_minutes' => env('PMS_POLL_INTERVAL_MINUTES', 15),

];
