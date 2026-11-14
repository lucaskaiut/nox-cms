<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --- Agendador (container "scheduler": php artisan schedule:work) ---
$tz = config('leads.google_ads.timezone');

Schedule::command('posts:publish-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('crm:task-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('crm:daily-digest')->dailyAt('08:00')->timezone($tz);
Schedule::command('nurture:process')->everyTenMinutes()->withoutOverlapping();
Schedule::command('nurture:process --enroll-lost')->dailyAt('09:30')->timezone($tz);
Schedule::command('google-ads:upload-conversions')->hourly()->withoutOverlapping();
Schedule::command('search-console:sync')->dailyAt('05:30')->timezone($tz)->withoutOverlapping();
