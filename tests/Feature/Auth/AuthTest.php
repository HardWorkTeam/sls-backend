<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $this->userWithRole(RoleKey::Couple, ['email' => 'couple@example.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'couple@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'token', 'user'])
            ->assertJsonPath('user.email', 'couple@example.com');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->userWithRole(RoleKey::Couple, ['email' => 'couple@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'couple@example.com',
            'password' => 'wrong-password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $this->userWithRole(RoleKey::Couple, [
            'email' => 'inactive@example.com',
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'This account has been deactivated.');
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_self(): void
    {
        $user = $this->userWithRole(RoleKey::Couple, ['email' => 'self@example.com']);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonFragment(['email' => 'self@example.com']);
    }

    public function test_logout_revokes_the_token(): void
    {
        $this->userWithRole(RoleKey::Couple, ['email' => 'revoke@example.com']);

        $token = $this->postJson('/api/auth/login', [
            'email' => 'revoke@example.com',
            'password' => 'password',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout')
            ->assertOk();

        // Drop the guard's cached user so the next request re-resolves the token
        // from the database (where logout has now deleted it).
        $this->app['auth']->forgetGuards();

        // The same token must no longer authenticate.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }
}
