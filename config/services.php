<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'coingecko' => [
        'api_key' => env('COINGECKO_API_KEY'),
        'base_url' => 'https://api.coingecko.com/api/v3',
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL', 'http://localhost:8000/api/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URL', 'http://localhost:8000/api/auth/facebook/callback'),
    ],

    'sms' => [
        'api_key' => env('SMS_API_KEY'),
        'base_url' => env('SMS_BASE_URL', 'https://sms-provider.az/api'),
    ],

    'push' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@alert.az'),
    ],

    'twelve_data' => [
        'api_key' => env('TWELVE_DATA_API_KEY'),
        'base_url' => 'https://api.twelvedata.com',
    ],

    'quicksms' => [
        'login' => env('QUICKSMS_LOGIN'),
        'password' => env('QUICKSMS_PASSWORD'),
        'base_url' => env('QUICKSMS_BASE_URL', 'https://apps.lsim.az/quicksms/v1'),
    ],

];
