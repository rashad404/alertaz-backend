<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// // Schedule exchange rates fetch daily at 12:00 PM
// Schedule::command('exchange-rates:fetch')->dailyAt('12:00');

// // Schedule bank rates fetch every 3 hours
// Schedule::command('bank-rates:fetch')->everyThreeHours();

// // Schedule crypto prices fetch every 5 minutes
// Schedule::command('crypto:fetch-prices')->everyFiveMinutes();

// // Schedule stock prices fetch every minute during market hours (9:30 AM - 4:00 PM ET)
// Schedule::command('stock:fetch-prices')->everyMinute()
//     ->between('09:30', '16:00')
//     ->weekdays()
//     ->withoutOverlapping()
//     ->runInBackground();



// Personal Alert Checks
// Check all alerts every minute (alerts have their own frequency control)
Schedule::command('alerts:check')->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// // Check crypto alerts more frequently during trading hours
// Schedule::command('alerts:check --type=crypto')->everyThirtySeconds()
//     ->between('09:00', '17:00')
//     ->weekdays()
//     ->withoutOverlapping()
//     ->runInBackground();

// Check website alerts every 2 minutes
Schedule::command('alerts:check --type=website')->everyTwoMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// // Check weather alerts every 10 minutes
// Schedule::command('alerts:check --type=weather')->everyTenMinutes()
//     ->withoutOverlapping()
//     ->runInBackground();

// // Check stock alerts every minute during market hours
// Schedule::command('alerts:check --type=stock')->everyMinute()
//     ->between('09:30', '16:00')
//     ->weekdays()
//     ->withoutOverlapping()
//     ->runInBackground();

// // Check currency alerts every 5 minutes
// Schedule::command('alerts:check --type=currency')->everyFiveMinutes()
//     ->withoutOverlapping()
//     ->runInBackground();

// Run automated SMS campaigns every minute
Schedule::exec('/usr/local/bin/ea-php82 ' . base_path('artisan') . ' campaigns:run-automated')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/campaigns.log'));
