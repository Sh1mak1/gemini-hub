<?php

return [

    'app_token' => env('PUSHOVER_APP_TOKEN'),

    'user_key' => env('PUSHOVER_USER_KEY'),

    /*
    | Pushover priority: -2 (no alert) .. 2 (emergency).
    | 0 is default notification.
    */
    'priority' => (int) env('PUSHOVER_PRIORITY', 0),

    'api_url' => 'https://api.pushover.net/1/messages.json',

];
