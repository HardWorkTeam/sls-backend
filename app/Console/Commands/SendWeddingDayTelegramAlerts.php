<?php

namespace App\Console\Commands;

use App\Enums\WeddingStatus;
use App\Models\Wedding;
use App\Services\TelegramNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendWeddingDayTelegramAlerts extends Command
{
    protected $signature = 'weddings:send-telegram-alerts
                            {--date= : Wedding date to alert for (YYYY-MM-DD)}
                            {--dry-run : Show the alert without sending it}';

    protected $description = 'Send Telegram alerts for published weddings happening today.';

    public function __construct(private readonly TelegramNotifier $telegram)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $timezone = (string) config('services.telegram.wedding_alert_timezone', 'Asia/Phnom_Penh');
        $date = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'), $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->startOfDay();

        $weddings = Wedding::query()
            ->where('status', WeddingStatus::Published->value)
            ->whereDate('wedding_date', $date->toDateString())
            ->orderBy('wedding_time')
            ->get();

        if ($weddings->isEmpty()) {
            $this->info("No published weddings on {$date->toDateString()}.");

            return self::SUCCESS;
        }

        $topicId = (int) config('services.telegram.wedding_alert_topic_id', 85);

        foreach ($weddings as $wedding) {
            $cacheKey = "telegram:wedding-day-alert:{$wedding->id}:{$date->toDateString()}";
            $message = $this->messageFor($wedding, $date);

            if ($this->option('dry-run')) {
                $this->line($message);

                continue;
            }

            if (Cache::has($cacheKey)) {
                $this->line("Skipped {$wedding->wedding_code}; alert was already sent.");

                continue;
            }

            if ($this->telegram->sendMessage($message, $topicId)) {
                // Retain this beyond midnight so a duplicate scheduler run can
                // never send the same wedding-day alert twice.
                Cache::put($cacheKey, true, $date->addDays(2)->endOfDay());
                $this->info("Sent alert for {$wedding->wedding_code} to topic {$topicId}.");
            } else {
                $this->warn("Could not send alert for {$wedding->wedding_code}.");
            }
        }

        return self::SUCCESS;
    }

    private function messageFor(Wedding $wedding, CarbonImmutable $date): string
    {
        $e = fn (?string $value): string => TelegramNotifier::escape($value);
        $time = $wedding->wedding_time ? substr((string) $wedding->wedding_time, 0, 5) : 'Time not set';
        $venue = $wedding->ceremony_venue ?? $wedding->reception_venue ?? 'Venue not set';

        return implode("\n", [
            '<b>Wedding day alert</b>',
            "<b>{$e($wedding->bride_name)} &amp; {$e($wedding->groom_name)}</b>",
            "Date: {$date->format('d M Y')} · {$e($time)}",
            "Venue: {$e($venue)}",
            "Code: <code>{$e($wedding->wedding_code)}</code>",
        ]);
    }
}
