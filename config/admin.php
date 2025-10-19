<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Panel Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the admin panel
    |
    */

    'default_language' => env('ADMIN_DEFAULT_LANGUAGE', 'en'),
    
    'available_languages' => [
        'az' => 'Azerbaijani',
        'en' => 'English',
        'ru' => 'Russian',
    ],
    
    'per_page' => 20,
    
    'date_format' => 'Y-m-d',
    
    'datetime_format' => 'Y-m-d H:i:s',
];