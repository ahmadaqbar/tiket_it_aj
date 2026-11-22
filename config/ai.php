<?php

/*
| Modul AI (opsional). Core ticketing berjalan penuh tanpa AI.
| AI aktif hanya bila: (1) setting `ai.enabled` dinyalakan IT Admin di System Settings, DAN
|                      (2) provider + API key + model terkonfigurasi di .env.
| API key HANYA di .env — tidak pernah disimpan di database, HTML, JavaScript, atau log.
*/

return [
    // anthropic | openai_compatible
    'provider' => env('AI_PROVIDER', 'anthropic'),
    'api_key' => env('AI_API_KEY'),
    'base_url' => env('AI_BASE_URL'),
    'model' => env('AI_MODEL'),

    'timeout' => (int) env('AI_TIMEOUT', 30),
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 1200),

    // Kendali biaya
    'rate_per_user_per_hour' => (int) env('AI_RATE_PER_USER_PER_HOUR', 30),
    'daily_limit' => (int) env('AI_DAILY_LIMIT', 300),

    'providers' => [
        'anthropic' => [
            'base_url' => 'https://api.anthropic.com',
            'model' => 'claude-opus-5-5',
            'version' => '2023-06-01',
        ],
        // Endpoint kompatibel OpenAI Chat Completions (OpenAI, Azure OpenAI proxy, OpenRouter, LLM lokal, dll.)
        // Model WAJIB diisi lewat AI_MODEL.
        'openai_compatible' => [
            'base_url' => 'https://api.openai.com/v1',
            'model' => null,
        ],
    ],
];
