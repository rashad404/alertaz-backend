<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used for
    | generating news articles. Supported: "claude", "openai", "gemini"
    |
    | Change AI_PROVIDER in .env to switch providers.
    |
    */

    'default_provider' => env('AI_PROVIDER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure the API settings for each AI provider.
    | Each provider requires an API key and has its own endpoint structure.
    |
    */

    'providers' => [

        'claude' => [
            // Support both single key and multiple keys (comma-separated)
            'api_keys' => array_filter(explode(',', env('ANTHROPIC_API_KEYS', env('ANTHROPIC_API_KEY', '')))),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'api_url' => 'https://api.anthropic.com/v1/messages',
            'max_tokens' => 2000,  // For news generation
            'max_tokens_parsing' => 500,  // For alert parsing
            'api_version' => '2023-06-01',
        ],

        'openai' => [
            // Support both single key and multiple keys (comma-separated)
            'api_keys' => array_filter(explode(',', env('OPENAI_API_KEYS', env('OPENAI_API_KEY', '')))),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'max_tokens' => 2000,  // For news generation
            'max_tokens_parsing' => 500,  // For alert parsing
        ],

        'gemini' => [
            // Support both single key and multiple keys (comma-separated)
            'api_keys' => array_filter(explode(',', env('GEMINI_API_KEYS', env('GEMINI_API_KEY', '')))),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),  // Flash for parsing, Pro for news
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'max_tokens' => 2000,  // For news generation
            'max_tokens_parsing' => 500,  // For alert parsing
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | News Generation Settings
    |--------------------------------------------------------------------------
    */

    'news' => [
        'min_words' => 30,
        'max_words' => 350,
        'default_language' => 'az',
    ],

    /*
    |--------------------------------------------------------------------------
    | Breaking News Thresholds
    |--------------------------------------------------------------------------
    |
    | Percentage change required to trigger breaking news articles
    |
    */

    'thresholds' => [
        'exchange_rates' => 0.5,  // 0.5% change triggers breaking news
        'oil' => 2.0,             // For future implementation
        'crypto' => 3.0,          // For future implementation
        'metals' => 1.0,          // For future implementation
        'stocks' => 2.5,          // For future implementation
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Parsing Settings
    |--------------------------------------------------------------------------
    |
    | Settings for intelligent alert parsing using LLM with smart caching
    |
    */

    'parsing' => [
        // Minimum confidence score to accept LLM parse result (0.0 - 1.0)
        'min_confidence' => 0.7,

        // Cache TTL in days (how long to keep cached patterns)
        'cache_ttl_days' => 90,

        // Maximum input length to parse
        'max_input_length' => 200,

        // Debounce delay in milliseconds (frontend waits before calling API)
        'debounce_ms' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Alert Services
    |--------------------------------------------------------------------------
    |
    | Configuration for each alert service type and their available operators
    |
    */

    'services' => [
        'crypto' => [
            'enabled' => true,
            'operators' => ['above', 'below', 'equals', 'changes_by'],
        ],
        'stocks' => [
            'enabled' => false,  // Not yet implemented
            'operators' => ['above', 'below', 'equals', 'changes_by'],
        ],
        'weather' => [
            'enabled' => false,  // Not yet implemented
            'operators' => ['above', 'below', 'equals'],
        ],
        'currency' => [
            'enabled' => false,  // Not yet implemented
            'operators' => ['above', 'below', 'equals', 'changes_by'],
        ],
        'website' => [
            'enabled' => false,  // Not yet implemented
            'operators' => ['changes', 'contains', 'not_contains'],
        ],
        'flight' => [
            'enabled' => false,  // Not yet implemented
            'operators' => ['price_below', 'available'],
        ],
    ],

];
