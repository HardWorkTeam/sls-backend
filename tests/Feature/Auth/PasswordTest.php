<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The auth routes share a 5/min IP throttle whose array-cache state
        // would otherwise leak across these tests and cause spurious 429s.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function login(string $email, string $password = 'password'): string
    {
        return $this->postJson('/api/auth/login', compact('email', 'password'))->json('token');
    }

    public function test_user_can_change_password_with_correct_current_password(): void
    {
        $this->userWithRole(RoleKey::Couple, ['email' => 'c@example.com']);
        $token = $this->login('c@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/password', [
                'current_password' => 'password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])->assertOk();

        // The new password authenticates; the old one no longer does.
        $this->postJson('/api/auth/login', ['email' => 'c@example.com', 'password' => 'new-secret-123'])
            ->assertOk();
        $this->postJson('/api/auth/login', ['email' => 'c@example.com', 'password' => 'password'])
            ->assertStatus(401);
    }

    public function test_change_password_rejects_a_wrong_current_password(): void
    {
        $this->userWithRole(RoleKey::Couple, ['email' => 'c@example.com']);
        $token = $this->login('c@example.com');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson('/api/auth/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_changing_password_revokes_other_sessions_but_keeps_the_current_one(): void
    {
        $this->userWithRole(RoleKey::Couple, ['email' => 'c@example.com']);
        $current = $this->login('c@example.com');
        $other = $this->login('c@example.com');

        $this->withHeader('Authorization', "Bearer {$current}")
            ->putJson('/api/auth/password', [
                'current_password' => 'password',
                'password' => 'new-secret-123',
                'password_confirmation' => 'new-secret-123',
            ])->assertOk();

        // Other session is revoked...
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$other}")
            ->getJson('/api/auth/me')->assertStatus(401);

        // ...the current session keeps working.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$current}")
            ->getJson('/api/auth/me')->assertOk();
    }

    public function test_forgot_password_creates_a_reset_token_for_a_known_email(): void
    {
        $this->userWithRole(RoleKey::Couple, ['email' => 'known@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'known@example.com'])
            ->assertOk();

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'known@example.com']);
    }

    public function test_forgot_password_rejects_an_unknown_email(): void
    {
        // Deliberate product decision (see AuthService::sendPasswordResetLink):
        // an unknown email is surfaced rather than returning a generic 200.
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = $this->userWithRole(RoleKey::Couple, ['email' => 'r@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'r@example.com',
            'password' => 'reset-secret-123',
            'password_confirmation' => 'reset-secret-123',
        ])->assertOk();

        $this->postJson('/api/auth/login', ['email' => 'r@example.com', 'password' => 'reset-secret-123'])
            ->assertOk();
    }

    public function test_reset_password_fails_with_an_invalid_token(): void
    {
        $this->userWithRole(RoleKey::Couple, ['email' => 'r@example.com']);

        $this->postJson('/api/auth/reset-password', [
            'token' => 'totally-invalid-token',
            'email' => 'r@example.com',
            'password' => 'reset-secret-123',
            'password_confirmation' => 'reset-secret-123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
