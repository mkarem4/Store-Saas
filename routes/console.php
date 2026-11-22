<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:expire')->everyFiveMinutes();
Schedule::command('wallet:release')->hourly()->withoutOverlapping();
Schedule::command('attendance:auto-close')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('gold:fetch')->hourlyAt(5)->withoutOverlapping();
Schedule::command('expenses:recurring')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('stock:alerts')->dailyAt('08:00')->withoutOverlapping();
