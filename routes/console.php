<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('scrape:due')->everyTenMinutes()->withoutOverlapping();
// Daily, so forecasts age even for listings whose price did not move.
Schedule::command('forecasts:refresh')->dailyAt('03:00');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
