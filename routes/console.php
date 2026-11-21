<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('scheduling:maintain --horizon')->dailyAt('03:00')->onOneServer();
Schedule::command('scheduling:maintain --reminders')->everyFifteenMinutes()->onOneServer();
