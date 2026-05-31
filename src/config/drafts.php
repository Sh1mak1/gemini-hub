<?php

return [

    'api_token' => env('DRAFTS_API_TOKEN'),

    'cache_ttl_minutes' => (int) env('DRAFTS_CACHE_TTL', 1440),

];
