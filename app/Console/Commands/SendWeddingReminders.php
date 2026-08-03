<?php

namespace App\Console\Commands;

use App\Services\WeddingReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Cron entry point for the couple countdown emails (one month out, one week
 * out, and on the wedding day).
 *
 * Safe to run as often as you like — every send is claimed in
 * `wedding_reminders` first, so a second run on the same day mails nobody.
 */
class SendWeddingReminders extends Command
{
    protected $signature = 'weddings:send-reminders
        {--date= : Treat this Y-m-d date as "today" (for backfills and testing)}
        {--dry-run : List what would be sent without sending or recording anything}';

    protected $description = 'Email couples a reminder one month, one week, and on the day of their wedding';

    public function handle(WeddingReminderService $reminders): int
    {
        // The wedding date is a plain calendar date, so "today" has to be
        // resolved in the couples' timezone. The app runs on UTC, where a
        // wedding-day email would fire at 07:00 local time on the wrong side
        // of midnight for anything scheduled near the day boundary.
        $timezone = (string) config('services.reminders.timezone', 'Asia/Phnom_Penh');

        try {
            $today = $this->option('date')
                ? Carbon::createFromFormat('Y-m-d', (string) $this->option('date'), $timezone)->startOfDay()
                : Carbon::now($timezone)->startOfDay();
        } catch (Throwable) {
            $this->error('Invalid --date. Expected format: Y-m-d (e.g. 2026-08-03).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Wedding reminders for %s (%s)%s',
            $today->toDateString(),
            $timezone,
            $dryRun ? ' — dry run, nothing will be sent' : '',
        ));

        $result = $reminders->sendDue($today, $dryRun);

        if ($result['details'] === []) {
            $this->line('No reminders due.');

            return self::SUCCESS;
        }

        $this->table(
            ['Wedding', 'Milestone', 'Outcome', 'Recipients', 'Note'],
            array_map(fn (array $row) => [
                $row['wedding_id'],
                $row['milestone'],
                $row['outcome'],
                implode(', ', $row['recipients']) ?: '—',
                $row['reason'] ?? '',
            ], $result['details']),
        );

        $this->info(sprintf(
            '%d sent, %d skipped, %d failed.',
            $result['sent'],
            $result['skipped'],
            $result['failed'],
        ));

        // A failed send is a real delivery problem, not a no-op: exit non-zero
        // so the cron host reports it instead of the failure going unseen.
        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
