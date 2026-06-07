<?php

return [

    'api_key' => env('GEMINI_API_KEY'),

    'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),

    'models' => [
        'flash' => env('GEMINI_MODEL_FLASH', 'gemini-2.5-flash'),
        'pro' => env('GEMINI_MODEL_PRO', 'gemini-2.5-pro'),
    ],

    'model_chain' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('GEMINI_MODEL_CHAIN', 'gemini-2.5-flash,gemini-2.0-flash,gemini-2.0-flash-lite')),
    ))),

    'retry' => [
        'max_attempts_per_model' => (int) env('GEMINI_RETRY_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('GEMINI_RETRY_BASE_DELAY_MS', 2000),
    ],

    'timeout' => (int) env('GEMINI_TIMEOUT', 60),

    'reference_timezone' => env('GEMINI_REFERENCE_TIMEZONE', 'Asia/Tokyo'),

];
