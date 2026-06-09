<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('slack:post-today-tasks')
    ->dailyAt(sprintf('%02d:00', (int) config('services.slack.today.post_hour', 9)))
    ->timezone(config('app.display_timezone', 'Asia/Tokyo'));
