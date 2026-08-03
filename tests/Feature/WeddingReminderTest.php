<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Enums\WeddingReminderMilestone;
use App\Enums\WeddingStatus;
use App\Mail\WeddingReminderMail;
use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingMember;
use App\Models\WeddingReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The countdown emails couples get one month out, one week out, and on the day.
 *
 * The behaviour that matters here is not "an email goes out" but everything
 * around it: the job runs on cron, so it will be invoked repeatedly, after
 * missed days, and against weddings that have been rescheduled or cancelled
 * since the last run. Each of those used to be a way to mail a couple twice —
 * or not at all — so each gets a test.
 */
class WeddingReminderTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = '2026-08-03';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['services.reminders.timezone' => 'Asia/Phnom_Penh']);
    }

    /**
     * A published wedding with one member whose account email is the recipient.
     */
    private function weddingIn(int $days, string $status = WeddingStatus::Published->value): Wedding
    {
        $wedding = Wedding::factory()->create([
            'status' => $status,
            'email' => null,
            'wedding_date' => Carbon::parse(self::TODAY)->addDays($days)->toDateString(),
        ]);

        WeddingMember::create([
            'wedding_id' => $wedding->id,
            'user_id' => User::factory()->create(['is_active' => true])->id,
            'member_role' => MemberRole::Bride->value,
            'is_primary' => true,
        ]);

        return $wedding->fresh();
    }

    private function runCommand(array $options = []): int
    {
        return $this->artisan('weddings:send-reminders', ['--date' => self::TODAY] + $options)->run();
    }

    public function test_it_sends_each_milestone_on_its_day(): void
    {
        $month = $this->weddingIn(30);
        $week = $this->weddingIn(7);
        $day = $this->weddingIn(0);

        $this->runCommand();

        Mail::assertSent(WeddingReminderMail::class, 3);

        foreach ([
            [$month, WeddingReminderMilestone::MonthBefore, 30],
            [$week, WeddingReminderMilestone::WeekBefore, 7],
            [$day, WeddingReminderMilestone::WeddingDay, 0],
        ] as [$wedding, $milestone, $daysRemaining]) {
            Mail::assertSent(
                WeddingReminderMail::class,
                fn (WeddingReminderMail $mail) => $mail->wedding->is($wedding)
                    && $mail->milestone === $milestone
                    && $mail->daysRemaining === $daysRemaining,
            );
        }
    }

    public function test_it_does_not_send_on_a_day_no_milestone_falls_on(): void
    {
        $this->weddingIn(15);

        $this->runCommand();

        Mail::assertNothingSent();
    }

    public function test_running_twice_on_the_same_day_sends_once(): void
    {
        $this->weddingIn(7);

        $this->runCommand();
        $this->runCommand();

        Mail::assertSent(WeddingReminderMail::class, 1);
        $this->assertSame(1, WeddingReminder::query()->count());
    }

    public function test_rescheduling_the_wedding_re_arms_the_milestone(): void
    {
        $wedding = $this->weddingIn(7);

        $this->runCommand();
        Mail::assertSent(WeddingReminderMail::class, 1);

        // The couple moves the wedding a fortnight later. Seven days before
        // the *new* date is a genuinely new reminder, not a duplicate of the
        // one already recorded against the old date.
        $wedding->forceFill([
            'wedding_date' => Carbon::parse(self::TODAY)->addDays(21)->toDateString(),
        ])->save();

        $this->artisan('weddings:send-reminders', [
            '--date' => Carbon::parse(self::TODAY)->addDays(14)->toDateString(),
        ])->run();

        Mail::assertSent(WeddingReminderMail::class, 2);
        $this->assertSame(2, WeddingReminder::query()->count());
    }

    public function test_it_catches_up_a_missed_run_and_reports_the_real_days_left(): void
    {
        // The cron did not run for two days, so this wedding is 5 days out
        // when the week-before reminder finally fires.
        $this->weddingIn(5);

        $this->runCommand();

        Mail::assertSent(
            WeddingReminderMail::class,
            fn (WeddingReminderMail $mail) => $mail->milestone === WeddingReminderMilestone::WeekBefore
                && $mail->daysRemaining === 5,
        );
    }

    public function test_it_does_not_catch_up_beyond_the_configured_window(): void
    {
        config(['services.reminders.catch_up_days' => 2]);

        $this->weddingIn(4);

        $this->runCommand();

        Mail::assertNothingSent();
    }

    public function test_it_never_sends_for_a_wedding_that_has_already_happened(): void
    {
        $this->weddingIn(-1);

        $this->runCommand();

        Mail::assertNothingSent();
    }

    public function test_it_skips_cancelled_and_completed_weddings(): void
    {
        $this->weddingIn(7, WeddingStatus::Cancelled->value);
        $this->weddingIn(7, WeddingStatus::Completed->value);

        $this->runCommand();

        Mail::assertNothingSent();
    }

    public function test_it_still_reminds_a_draft_wedding(): void
    {
        // A draft a month out is exactly the couple who needs the nudge —
        // their guests cannot RSVP until they publish.
        $this->weddingIn(30, WeddingStatus::Draft->value);

        $this->runCommand();

        Mail::assertSent(WeddingReminderMail::class, 1);
    }

    public function test_it_mails_every_member_and_the_wedding_contact_once(): void
    {
        $wedding = $this->weddingIn(7);
        $bride = $wedding->members->first()->user;

        $groom = User::factory()->create(['is_active' => true, 'email' => 'groom@example.com']);
        WeddingMember::create([
            'wedding_id' => $wedding->id,
            'user_id' => $groom->id,
            'member_role' => MemberRole::Groom->value,
            'is_primary' => false,
        ]);

        // The wedding's contact address duplicates the bride's (in a different
        // case) and adds one more address nobody has an account for.
        $wedding->forceFill(['email' => strtoupper($bride->email)])->save();

        $this->runCommand();

        Mail::assertSent(WeddingReminderMail::class, 2);
        Mail::assertSent(
            WeddingReminderMail::class,
            fn (WeddingReminderMail $mail) => $mail->hasTo($bride->email),
        );
        Mail::assertSent(
            WeddingReminderMail::class,
            fn (WeddingReminderMail $mail) => $mail->hasTo('groom@example.com'),
        );
    }

    public function test_it_skips_deactivated_members(): void
    {
        $wedding = $this->weddingIn(7);
        $wedding->members->first()->user->forceFill(['is_active' => false])->save();

        $this->runCommand();

        Mail::assertNothingSent();
        $this->assertSame(0, WeddingReminder::query()->count());
    }

    public function test_a_wedding_with_no_reachable_address_is_left_for_the_next_run(): void
    {
        $wedding = Wedding::factory()->create([
            'status' => WeddingStatus::Published->value,
            'email' => null,
            'wedding_date' => Carbon::parse(self::TODAY)->addDays(7)->toDateString(),
        ]);

        $this->runCommand();

        Mail::assertNothingSent();

        // No claim was recorded, so adding an address later still gets the
        // couple their reminder instead of it being silently marked done.
        $this->assertSame(0, WeddingReminder::query()->count());

        $wedding->forceFill(['email' => 'couple@example.com'])->save();
        $this->runCommand();

        Mail::assertSent(WeddingReminderMail::class, 1);
    }

    public function test_dry_run_sends_nothing_and_records_nothing(): void
    {
        $this->weddingIn(7);

        $this->runCommand(['--dry-run' => true]);

        Mail::assertNothingSent();
        $this->assertSame(0, WeddingReminder::query()->count());

        // ...and the reminder is still pending afterwards.
        $this->runCommand();
        Mail::assertSent(WeddingReminderMail::class, 1);
    }

    public function test_the_email_renders_with_the_wedding_details(): void
    {
        Mail::fake();

        $wedding = $this->weddingIn(7);
        $wedding->forceFill([
            'wedding_name' => 'Sokha & Dara',
            'ceremony_venue' => 'Wat Phnom Hall',
            'wedding_time' => '17:30:00',
        ])->save();

        $mail = new WeddingReminderMail(
            wedding: $wedding->fresh(),
            milestone: WeddingReminderMilestone::WeekBefore,
            daysRemaining: 7,
            stats: ['total_guests' => 120, 'accepted' => 80, 'awaiting' => 40],
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Sokha &amp; Dara', $rendered);
        $this->assertStringContainsString('Wat Phnom Hall', $rendered);
        $this->assertStringContainsString('5:30 PM', $rendered);
        $this->assertStringContainsString('120', $rendered);
        $this->assertSame(
            'One week to go — Sokha & Dara',
            $mail->envelope()->subject,
        );
    }
}
