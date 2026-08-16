<?php

return [

    'enabled' => env('AI_ENABLED', true),

    'provider' => env('AI_PROVIDER', 'openai'),

    'api_key' => env('OPENAI_API_KEY'),

    'model' => env('AI_MODEL', 'gpt-4o-mini'),

    'base_url' => rtrim(env('AI_BASE_URL', 'https://api.openai.com/v1'), '/'),

    'timeout' => (int) env('AI_TIMEOUT', 60),

    'fetch_timeout' => (int) env('AI_FETCH_TIMEOUT', 15),

    'max_fetch_bytes' => (int) env('AI_MAX_FETCH_BYTES', 524288),

    'free_monthly_limit' => (int) env('AI_FREE_MONTHLY_LIMIT', 1),

    'user_agent' => env('AI_USER_AGENT', 'DocsBot/1.0'),

];
