<?php

namespace App\Enums;

/**
 * The countdown milestones the platform emails couples about.
 *
 * The case *value* is what gets persisted in `wedding_reminders.milestone`, so
 * renaming one would orphan every row already sent under the old name and the
 * couple would be emailed again. Add cases freely; never rename them.
 */
enum WeddingReminderMilestone: string
{
    case MonthBefore = 'month_before';
    case WeekBefore = 'week_before';
    case WeddingDay = 'wedding_day';

    /**
     * How many days ahead of the wedding this milestone fires. Ordered
     * furthest-out first so a single pass over the cases sends the earliest
     * reminder first when several happen to be due on the same run.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function daysBefore(): int
    {
        return match ($this) {
            self::MonthBefore => 30,
            self::WeekBefore => 7,
            self::WeddingDay => 0,
        };
    }

    /**
     * Subject line for the reminder email. Kept here rather than in the
     * Mailable so the copy for a milestone lives next to its definition.
     */
    public function subject(string $weddingName): string
    {
        return match ($this) {
            self::MonthBefore => "One month to go — {$weddingName}",
            self::WeekBefore => "One week to go — {$weddingName}",
            self::WeddingDay => "Today's the day — {$weddingName}",
        };
    }

    public function headline(): string
    {
        return match ($this) {
            self::MonthBefore => 'One month to go',
            self::WeekBefore => 'One week to go',
            self::WeddingDay => 'Today is the big day',
        };
    }

    /**
     * Short, milestone-appropriate nudges shown as a checklist in the email.
     *
     * @return list<string>
     */
    public function checklist(): array
    {
        return match ($this) {
            self::MonthBefore => [
                'Send your invitations if you have not already — guests need time to reply.',
                'Chase the guests who have not responded yet.',
                'Confirm the venue, catering and photographer bookings.',
                'Start assigning guests to tables.',
            ],
            self::WeekBefore => [
                'Give the venue and caterer your final head count.',
                'Finish the seating plan and print the table cards.',
                'Confirm the run of the day with everyone involved.',
                'Get the check-in QR scanner ready for the door team.',
            ],
            self::WeddingDay => [
                'Open the guest list on your phone to check guests in at the door.',
                'Share the invitation link once more for anyone still on the way.',
                'Enjoy it — everything is in place.',
            ],
        };
    }
}
