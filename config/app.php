<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'Asia/Baku',
    'locales' => ['az'],

    /*
    |--------------------------------------------------------------------------
    | Verification Mock Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, verification codes (SMS/Email) will be mocked for testing.
    | The mock code "123456" will always be accepted in local environment.
    | This is automatically enabled in local environment for easy testing.
    |
    */

    'verification_mock' => env('VERIFICATION_MOCK_MODE', env('APP_ENV') === 'local'),
    'mock_verification_code' => env('MOCK_VERIFICATION_CODE', '123456'),

    /*
    |--------------------------------------------------------------------------
    | Notifications Mock Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, all alert notifications will be mocked (logged but not sent).
    | This includes Email, SMS, Telegram, WhatsApp, Slack, and Push notifications.
    | Automatically enabled in local environment to save costs and avoid spam.
    |
    */

    'notifications_mock' => env('NOTIFICATIONS_MOCK_MODE', env('APP_ENV') === 'local'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS API Configuration
    |--------------------------------------------------------------------------
    |
    | Cost per SMS message in AZN. This value can be easily changed.
    | Default senders available for all users.
    |
    */

    'sms_cost_per_message' => (float) env('SMS_COST_PER_MESSAGE', 0.04),
    'sms_max_message_length' => (int) env('SMS_MAX_MESSAGE_LENGTH', 500),
    'sms_chars_per_segment' => (int) env('SMS_CHARS_PER_SEGMENT', 153),
    'sms_rate_limit_per_second' => (int) env('SMS_RATE_LIMIT_PER_SECOND', 10),
    'campaign_confirmation_threshold' => (int) env('CAMPAIGN_CONFIRMATION_THRESHOLD', 1000),

    'sms_default_senders' => ['Alert.az', 'Sayt.az', 'Task.az'],

];
