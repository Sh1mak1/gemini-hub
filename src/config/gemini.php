<?php

return [

    'api_key' => env('GEMINI_API_KEY'),

    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    'models' => [
        'flash' => env('GEMINI_MODEL_FLASH', 'gemini-2.5-flash'),
        'pro' => env('GEMINI_MODEL_PRO', 'gemini-2.5-pro'),
    ],

    'timeout' => (int) env('GEMINI_TIMEOUT', 60),

    'reference_timezone' => env('GEMINI_REFERENCE_TIMEZONE', 'Asia/Tokyo'),

];
