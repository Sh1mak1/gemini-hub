<?php

return [

    'api_token' => env('DRAFTS_API_TOKEN'),

    'cache_ttl_minutes' => (int) env('DRAFTS_CACHE_TTL', 1440),

    'queue_retry_delays_minutes' => [
        'second' => 2,
        'third' => 5,
        'fourth' => 15,
        'max' => 60,
    ],

];
