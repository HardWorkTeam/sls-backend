<?php

namespace Tests\Feature;

use App\Enums\WeddingStatus;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeddingDayTelegramAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.telegram.bot_token', 'test-token');
        config()->set('services.telegram.admin_chat_id', '-100123');
        config()->set('services.telegram.wedding_alert_topic_id', 85);
        Cache::flush();
        Http::fake();
    }

    public function test_it_alerts_only_published_weddings_happening_on_the_requested_day_in_topic_85(): void
    {
        $wedding = Wedding::factory()->create([
            'status' => WeddingStatus::Published->value,
            'wedding_date' => '2026-08-12',
            'wedding_time' => '17:30:00',
            'bride_name' => 'Sophea',
            'groom_name' => 'Visal',
            'ceremony_venue' => 'Sokha Hotel',
        ]);

        Wedding::factory()->create([
            'status' => WeddingStatus::Draft->value,
            'wedding_date' => '2026-08-12',
        ]);
        Wedding::factory()->create([
            'status' => WeddingStatus::Published->value,
            'wedding_date' => '2026-08-13',
        ]);

        $this->artisan('weddings:send-telegram-alerts', ['--date' => '2026-08-12'])
            ->expectsOutput("Sent alert for {$wedding->wedding_code} to topic 85.")
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && $request['chat_id'] === '-100123'
                && $request['message_thread_id'] === 85
                && str_contains($request['text'], 'Sophea &amp; Visal');
        });
    }

    public function test_it_does_not_resend_an_alert_for_the_same_wedding_day(): void
    {
        Wedding::factory()->create([
            'status' => WeddingStatus::Published->value,
            'wedding_date' => '2026-08-12',
        ]);

        $this->artisan('weddings:send-telegram-alerts', ['--date' => '2026-08-12'])->assertSuccessful();
        $this->artisan('weddings:send-telegram-alerts', ['--date' => '2026-08-12'])->assertSuccessful();

        Http::assertSentCount(1);
    }
}
