<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule exchange rates fetch daily at 12:00 PM
Schedule::command('exchange-rates:fetch')->dailyAt('12:00');

// Schedule bank rates fetch every 3 hours
Schedule::command('bank-rates:fetch')->everyThreeHours();

// Schedule crypto prices fetch every 5 minutes
Schedule::command('crypto:fetch-prices')->everyFiveMinutes();

// AI News Generation - Daily summary at 5:00 PM
Schedule::command('news:generate-exchange-summary')->dailyAt('17:00');

// AI News Generation - Check for breaking news every 2 hours
Schedule::command('news:check-exchange-breaking')->everyTwoHours();
