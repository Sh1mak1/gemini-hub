<?php

return [

    'api_key' => env('OPENROUTER_API_KEY'),

    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),

    'model' => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash-lite'),

    'timeout' => (int) env('OPENROUTER_TIMEOUT', 25),

    'retry_delay_ms' => (int) env('OPENROUTER_RETRY_DELAY_MS', 400),

    /*
     * Stray Signal だけが参照する。普段は gemini。試し打ちのときだけ openrouter。
     */
    'contact_provider' => env('CONTACT_LLM_PROVIDER', 'gemini'),

];
