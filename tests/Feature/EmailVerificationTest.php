<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['key' => RoleKey::Couple->value, 'name' => 'Couple']);
    }

    public function test_registration_sends_a_verification_email_and_does_not_issue_a_token(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'New Couple',
            'email' => 'new@example.com',
            'password' => 'StrongPass1',
            'password_confirmation' => 'StrongPass1',
        ])->assertCreated()
            ->assertJsonMissingPath('token')
            ->assertJsonPath('user.email_verified_at', null);

        $user = User::firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->postJson('/api/auth/login', [
            'email' => 'new@example.com',
            'password' => 'StrongPass1',
            'portal' => 'couple',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_signed_verification_link_marks_the_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect(config('services.client.url').'/verify-email?verified=1');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
