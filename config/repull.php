<?php

/*
|--------------------------------------------------------------------------
| Repull (channel connectivity + listing content push)
|--------------------------------------------------------------------------
| One Repull workspace + one API key for the whole GuestHub platform. Every
| host connects THEIR OWN Airbnb / Booking.com / Vrbo account into that
| workspace through Repull's hosted Connect flow. The key is workspace-wide,
| so host isolation is enforced HERE (channel_connections.owner_id), never by
| Repull. Keep the key server-side only.
|
| Repull has no sandbox: every key acts on real channel traffic. Use a
| throwaway OTA test account until the verification gate in docs/CHANNEL_SYNC_PLAN.md
| (section 4) is closed.
*/
return [
    'enabled' => (bool) env('REPULL_ENABLED', false),
    'base_url' => env('REPULL_BASE_URL', 'https://api.repull.dev'),
    'api_key' => env('REPULL_API_KEY'),
    // Signing secret returned once when the webhook subscription is created.
    'webhook_secret' => env('REPULL_WEBHOOK_SECRET'),
    'timeout' => (int) env('REPULL_TIMEOUT', 20),
    // Login-based Vrbo beta: enable only after throwaway-account and terms verification.
    'vrbo_publish_enabled' => (bool) env('REPULL_VRBO_PUBLISH_ENABLED', false),
    // Max age (seconds) of a webhook timestamp before it is rejected as a replay.
    'webhook_tolerance' => (int) env('REPULL_WEBHOOK_TOLERANCE', 300),
];
