<?php

return [

    /*
    | Outgoing webhooks. Targets must use HTTPS and resolve to public IP addresses
    | (SSRF protection); private/loopback targets can be allowed for local testing only.
    */
    'allow_private_targets' => (bool) env('WEBHOOKS_ALLOW_PRIVATE_TARGETS', false),

    'timeout' => (int) env('WEBHOOKS_TIMEOUT', 10),

    // Endpoints are switched off after this many failed deliveries in a row.
    'disable_after_failures' => 20,

    // Retry delays in seconds; the number of entries + 1 is the number of attempts.
    'backoff' => [30, 120, 600, 3600],

];
