<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    /*
    | Stateless Python analytics service (see /analytics). Requests are HMAC-signed
    | with the shared secret; results are cached per school for `cache_minutes`.
    */
    'analytics' => [
        'url' => env('ANALYTICS_URL', 'http://127.0.0.1:8100'),
        'secret' => env('ANALYTICS_SHARED_SECRET'),
        'timeout' => (int) env('ANALYTICS_TIMEOUT', 10),
        'cache_minutes' => (int) env('ANALYTICS_CACHE_MINUTES', 10),
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

];
