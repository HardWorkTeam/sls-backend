<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\User;
use App\Models\Wedding;
use App\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Editing an invitation clears our own cached public payload immediately, then
 * tells the RSVP site to drop its copy. The second half is an outbound HTTP
 * call, so it is deferred until after the response — these tests pin down both
 * the timing and the de-duplication.
 */
class InvitationRevalidationTest extends TestCase
{
    use RefreshDatabase;

    private Wedding $wedding;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rsvp.revalidate_secret' => 'test-secret',
            'services.rsvp.url' => 'https://rsvp.test',
        ]);

        Http::fake();

        $user = User::factory()->create(['is_active' => true]);
        $this->wedding = Wedding::factory()->create(['created_by_user_id' => $user->id]);
    }

    public function test_our_own_cache_is_cleared_before_the_response_is_sent(): void
    {
        $invitation = Invitation::factory()->create(['wedding_id' => $this->wedding->id]);
        $key = InvitationService::publicCacheKey($invitation->invitation_code);

        Cache::put($key, ['stale' => true], 60);

        app(InvitationService::class)->update($invitation, ['title' => 'Updated']);

        // Synchronous: the API must never serve the stale payload, even if the
        // deferred ping below never runs.
        $this->assertNull(Cache::get($key));
        Http::assertNothingSent();
    }

    public function test_the_rsvp_site_is_pinged_once_the_request_terminates(): void
    {
        $invitation = Invitation::factory()->create(['wedding_id' => $this->wedding->id]);

        app(InvitationService::class)->update($invitation, ['title' => 'Updated']);

        $this->app->terminate();

        Http::assertSent(fn ($request) => $request->url() === 'https://rsvp.test/api/revalidate'
            && $request['code'] === $invitation->invitation_code
            && $request->header('x-revalidate-secret') === ['test-secret']);
    }

    public function test_a_wedding_wide_flush_pings_each_code_exactly_once(): void
    {
        $service = app(InvitationService::class);

        $invitations = Invitation::factory()->count(3)->create([
            'wedding_id' => $this->wedding->id,
        ]);

        // Two flushes in one request (e.g. a status change that re-runs the
        // clear) must not double the outbound calls.
        $service->forgetWeddingPublicCaches($this->wedding);
        $service->forgetWeddingPublicCaches($this->wedding);

        Http::assertNothingSent();

        $this->app->terminate();

        Http::assertSentCount($invitations->count());
    }

    public function test_nothing_is_sent_when_no_revalidate_secret_is_configured(): void
    {
        config(['services.rsvp.revalidate_secret' => null]);

        $invitation = Invitation::factory()->create(['wedding_id' => $this->wedding->id]);

        app(InvitationService::class)->update($invitation, ['title' => 'Updated']);
        $this->app->terminate();

        Http::assertNothingSent();
    }
}
