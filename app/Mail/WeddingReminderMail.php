<?php

namespace App\Mail;

use App\Enums\WeddingReminderMilestone;
use App\Models\Wedding;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The countdown email sent to a couple a month out, a week out and on the day.
 *
 * Not queued: the platform has no queue worker running (see docker/start.sh),
 * so a ShouldQueue mailable would be written to the `jobs` table and never
 * picked up. The command that builds these runs on cron, off the request path,
 * where a synchronous send is fine.
 */
class WeddingReminderMail extends Mailable
{
    /**
     * @param  array<string, int>  $stats  guest/RSVP counts rendered in the email
     */
    public function __construct(
        public readonly Wedding $wedding,
        public readonly WeddingReminderMilestone $milestone,
        public readonly int $daysRemaining,
        public readonly array $stats = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->milestone->subject($this->wedding->wedding_name),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wedding-reminder',
            with: [
                'wedding' => $this->wedding,
                'milestone' => $this->milestone,
                'daysRemaining' => $this->daysRemaining,
                'stats' => $this->stats,
                'time' => $this->formattedTime(),
                'portalUrl' => rtrim((string) config('services.client.url'), '/').'/my-wedding',
            ],
        );
    }

    /**
     * `wedding_time` is a bare TIME column read back as a string, and its shape
     * differs by driver ("18:30:00" on Postgres, whatever was stored on
     * SQLite). Parse defensively and fall back to the raw value rather than
     * letting a malformed time abort the whole reminder.
     */
    private function formattedTime(): ?string
    {
        $raw = trim((string) $this->wedding->wedding_time);

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->format('g:i A');
        } catch (Throwable) {
            return $raw;
        }
    }
}
