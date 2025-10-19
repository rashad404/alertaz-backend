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
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'api_url' => 'https://api.anthropic.com/v1/messages',
            'max_tokens' => 2000,
            'api_version' => '2023-06-01',
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'max_tokens' => 2000,
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-pro'),
            'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'max_tokens' => 2000,
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

];
