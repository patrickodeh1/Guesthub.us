<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'seam' => [
        'api_key' => env('SEAM_API_KEY'),
        'webhook_secret' => env('SEAM_WEBHOOK_SECRET'),
    ],

    'telnyx' => [
        'api_key' => env('TELNYX_API_KEY'),
        'public_key' => env('TELNYX_PUBLIC_KEY'),
        'from_number' => env('TELNYX_FROM_NUMBER'),
        'messaging_profile_id' => env('TELNYX_MESSAGING_PROFILE_ID'),
        'admin_notify_number' => env('TELNYX_ADMIN_NOTIFY_NUMBER'),
    ],

    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    // Used by IdDocumentExtractor to OCR guest ID photos (name/DOB/expiry).
    // A plain API key is enough for the Vision REST API — no service
    // account JSON needed. First 1,000 DOCUMENT_TEXT_DETECTION calls/month
    // are free; a GCP billing account with a card on file is still required
    // to turn the API on, even to use the free allowance.
    'google_vision' => [
        'key' => env('GOOGLE_VISION_API_KEY'),
    ],

    'channex' => [
        'api_key' => env('CHANNEX_API_KEY'),
        'base_url' => env('CHANNEX_BASE_URL', 'https://staging.channex.io/api/v1'),
        'webhook_secret' => env('CHANNEX_WEBHOOK_SECRET'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

];
