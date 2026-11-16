<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blind Index Key
    |--------------------------------------------------------------------------
    |
    | Encrypted columns cannot be searched. For fields that need exact-match
    | lookups (phone, national ID) we store a keyed HMAC "blind index" next to
    | the ciphertext. This key MUST differ from APP_KEY, and rotating it
    | requires recomputing every stored index.
    | When empty, a key is derived from APP_KEY with a domain separator.
    |
    */

    'blind_index_key' => env('BLIND_INDEX_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | Limits where the browser may load scripts, styles, images and fonts from,
    | so an injected <script> cannot run. Scripts need a per-request nonce.
    | Off by default in local development (the Vite dev server and debug
    | tooling inject their own scripts). Set CSP_REPORT_ONLY=true to watch
    | violations in the browser console without blocking anything.
    | CSP_IMG_SOURCES adds space-separated image hosts (e.g. an S3 bucket URL).
    |
    */

    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', env('APP_ENV') !== 'local'),
        'report_only' => (bool) env('CSP_REPORT_ONLY', false),
        'img_sources' => array_values(array_filter(explode(' ', (string) env('CSP_IMG_SOURCES', '')))),
    ],

];
