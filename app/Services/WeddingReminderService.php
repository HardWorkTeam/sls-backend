<?php

namespace App\Services;

use App\Enums\RsvpStatus;
use App\Enums\WeddingReminderMilestone;
use App\Enums\WeddingStatus;
use App\Mail\WeddingReminderMail;
use App\Models\Guest;
use App\Models\RsvpResponse;
use App\Models\Wedding;
use App\Models\WeddingReminder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails couples a countdown reminder a month before, a week before and on
 * their wedding day.
 *
 * Driven by cron via the `weddings:send-reminders` command, so everything here
 * is written to be safe to run repeatedly: each send is claimed by a unique row
 * in `wedding_reminders` before the mail goes out, and the claim is rolled back
 * if the provider rejects it so the next run retries.
 */
class WeddingReminderService
{
    /**
     * Statuses whose weddings still deserve a countdown. A cancelled wedding
     * is off, and a completed one already happened — mailing either would be
     * worse than staying quiet.
     *
     * @var list<string>
     */
    private const REMINDABLE_STATUSES = [
        WeddingStatus::Draft->value,
        WeddingStatus::Published->value,
    ];

    /**
     * Send every reminder that is due as of `$today` (a date in the platform's
     * reminder timezone, not UTC).
     *
     * @return array{sent: int, skipped: int, failed: int, details: list<array<string, mixed>>}
     */
    public function sendDue(Carbon $today, bool $dryRun = false): array
    {
        $today = $today->copy()->startOfDay();
        $catchUpDays = max(0, (int) config('services.reminders.catch_up_days', 2));

        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'details' => []];

        foreach (WeddingReminderMilestone::cases() as $milestone) {
            foreach ($this->dueWeddings($milestone, $today, $catchUpDays) as $wedding) {
                $outcome = $this->sendFor($wedding, $milestone, $today, $dryRun);

                $result[$outcome['outcome']]++;
                $result['details'][] = $outcome;
            }
        }

        return $result;
    }

    /**
     * Weddings whose date lands in this milestone's window and that have not
     * already been mailed for it.
     *
     * The window is a range rather than an exact date so a cron run that was
     * missed (deploy, host restart) still delivers the reminder on the next
     * run instead of skipping it forever. It never extends into the past —
     * a "one week to go" email the day after the wedding helps nobody — and
     * the email quotes the real number of days left, so a late send reads
     * honestly as "5 days" rather than claiming a week.
     *
     * @return Collection<int, Wedding>
     */
    private function dueWeddings(WeddingReminderMilestone $milestone, Carbon $today, int $catchUpDays): Collection
    {
        $windowEnd = $today->copy()->addDays($milestone->daysBefore());
        $windowStart = $windowEnd->copy()->subDays($catchUpDays);

        if ($windowStart->lessThan($today)) {
            $windowStart = $today->copy();
        }

        // whereDate, not whereBetween: `wedding_date` is a DATE column in
        // Postgres but the `date` cast writes a full "Y-m-d H:i:s" string,
        // which SQLite (the test driver) stores verbatim. A plain string
        // comparison then puts "2026-08-10 00:00:00" *after* the "2026-08-10"
        // upper bound and silently drops every wedding that lands exactly on
        // the milestone. whereDate normalizes both sides on either driver.
        return Wedding::query()
            ->whereIn('status', self::REMINDABLE_STATUSES)
            ->whereNotNull('wedding_date')
            ->whereDate('wedding_date', '>=', $windowStart->toDateString())
            ->whereDate('wedding_date', '<=', $windowEnd->toDateString())
            ->whereDoesntHave(
                'reminders',
                fn ($query) => $query
                    ->where('milestone', $milestone->value)
                    ->whereColumn('wedding_reminders.wedding_date', 'weddings.wedding_date'),
            )
            ->with(['members.user'])
            ->orderBy('wedding_date')
            ->get();
    }

    /**
     * Claim, build and send one reminder.
     *
     * @return array{outcome: 'sent'|'skipped'|'failed', wedding_id: int, milestone: string, recipients: list<string>, reason?: string}
     */
    private function sendFor(Wedding $wedding, WeddingReminderMilestone $milestone, Carbon $today, bool $dryRun): array
    {
        $recipients = $this->recipientsFor($wedding);

        $base = [
            'wedding_id' => (int) $wedding->id,
            'milestone' => $milestone->value,
            'recipients' => $recipients,
        ];

        if ($recipients === []) {
            // Nothing to mail, and no row written: if the couple adds an email
            // address tomorrow the reminder is still waiting for them.
            return $base + ['outcome' => 'skipped', 'reason' => 'no recipients'];
        }

        if ($dryRun) {
            return $base + ['outcome' => 'skipped', 'reason' => 'dry run'];
        }

        // Claim the send before mailing. The unique index on
        // (wedding_id, milestone, wedding_date) makes this the lock: if two
        // cron runs overlap, the second one loses here and skips instead of
        // sending the couple a duplicate. Any other query failure is a real
        // problem, so it is logged rather than silently treated as a claim.
        try {
            $reminder = WeddingReminder::create([
                'wedding_id' => $wedding->id,
                'milestone' => $milestone->value,
                'wedding_date' => $wedding->wedding_date,
                'recipients' => $recipients,
                'sent_at' => null,
            ]);
        } catch (QueryException $e) {
            Log::debug('Wedding reminder claim rejected', [
                'wedding_id' => $wedding->id,
                'milestone' => $milestone->value,
                'error' => $e->getMessage(),
            ]);

            return $base + ['outcome' => 'skipped', 'reason' => 'already claimed'];
        }

        $daysRemaining = (int) $today->diffInDays($wedding->wedding_date->copy()->startOfDay(), absolute: false);
        $stats = $this->statsFor($wedding);

        // One message per address rather than a single multi-recipient send.
        // Brevo rejects an entire message when any recipient is refused, so
        // batching would let one stale address suppress the reminder for the
        // whole couple — and it would also expose everyone's address in the
        // To: header.
        $delivered = [];
        $errors = [];

        foreach ($recipients as $address) {
            try {
                Mail::to($address)->send(new WeddingReminderMail(
                    wedding: $wedding,
                    milestone: $milestone,
                    daysRemaining: max(0, $daysRemaining),
                    stats: $stats,
                ));

                $delivered[] = $address;
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();

                Log::warning('Wedding reminder failed for one recipient', [
                    'wedding_id' => $wedding->id,
                    'milestone' => $milestone->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($delivered === []) {
            // Nobody was reached — release the claim so the next run retries
            // rather than leaving a reminder recorded that never arrived.
            $reminder->delete();

            return $base + ['outcome' => 'failed', 'reason' => $errors[0] ?? 'no recipient accepted'];
        }

        // At least one address took it. Record who actually received it, so a
        // partial delivery is visible afterwards instead of looking complete.
        $reminder->forceFill(['recipients' => $delivered, 'sent_at' => now()])->save();

        return array_merge($base, [
            'outcome' => 'sent',
            'recipients' => $delivered,
        ]);
    }

    /**
     * Everyone attached to the wedding: each active member's account email,
     * plus the wedding's own contact address. Deduplicated case-insensitively
     * so a member whose account email matches the wedding contact is mailed
     * once, not twice.
     *
     * @return list<string>
     */
    public function recipientsFor(Wedding $wedding): array
    {
        $addresses = $wedding->members
            ->map(fn ($member) => $member->user)
            ->filter(fn ($user) => $user !== null && $user->is_active)
            ->pluck('email')
            ->push($wedding->email)
            ->filter(fn (?string $email) => is_string($email)
                && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false)
            ->map(fn (string $email) => trim($email));

        // Dedupe on the lowercased address but keep the first spelling seen.
        // Members come first, so an account email wins over the same address
        // retyped in the wedding's free-text contact field.
        return $addresses
            ->unique(fn (string $email) => mb_strtolower($email))
            ->values()
            ->all();
    }

    /**
     * Guest-list numbers shown in the email.
     *
     * "Awaiting" is derived rather than counted: RSVPs can arrive from the
     * public invitation without being tied to a row on the guest list
     * (`rsvp_responses.guest_id` is nullable), so there is no reliable
     * "guests with no response" query. Invited-minus-replied is the number a
     * couple actually wants, floored at zero for the case where more people
     * replied than were formally invited.
     *
     * @return array<string, int>
     */
    public function statsFor(Wedding $wedding): array
    {
        $totalGuests = Guest::query()->where('wedding_id', $wedding->id)->count();

        $byStatus = RsvpResponse::query()
            ->where('wedding_id', $wedding->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $responded = (int) $byStatus->sum();

        return [
            'total_guests' => $totalGuests,
            'accepted' => (int) ($byStatus[RsvpStatus::Accepted->value] ?? 0),
            'declined' => (int) ($byStatus[RsvpStatus::Declined->value] ?? 0),
            'responded' => $responded,
            'awaiting' => max(0, $totalGuests - $responded),
        ];
    }
}
