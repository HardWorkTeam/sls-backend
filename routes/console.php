<?php

use App\Console\Commands\SendWeddingReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Countdown emails to couples: one month out, one week out, and on the day.
 *
 * Something has to call `php artisan schedule:run` every minute for this entry
 * to fire — the API container runs Apache only, no scheduler (docker/start.sh).
 * A cron host may equally invoke `weddings:send-reminders` directly once a day
 * and skip the scheduler; the command is idempotent either way.
 *
 * withoutOverlapping matters because the send is not queued: a run still
 * talking to Brevo must not be joined by the next minute's run. Both locks use
 * the database cache store (see config/cache.php), so no Redis is required.
 */
Schedule::command(SendWeddingReminders::class)
    ->dailyAt((string) config('services.reminders.send_at', '08:00'))
    ->timezone((string) config('services.reminders.timezone', 'Asia/Phnom_Penh'))
    ->withoutOverlapping()
    ->onOneServer();
