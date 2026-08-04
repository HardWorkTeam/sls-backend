<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('weddings:send-telegram-alerts')
    ->dailyAt((string) config('services.telegram.wedding_alert_send_at', '08:00'))
    ->timezone((string) config('services.telegram.wedding_alert_timezone', 'Asia/Phnom_Penh'));
