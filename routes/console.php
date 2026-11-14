<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
// Every hour, close reservations nobody answered before their day ended.
Schedule::command('reservations:expire')->hourly();
// Five minutes past every hour, re-rank shops for the homepage.
Schedule::command('shops:score')->hourlyAt(5);
