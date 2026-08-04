<?php

namespace App\Services;

use App\Enums\RoleKey;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Throwable;

class AuthService
{
    private const TOKEN_NAME_FALLBACK = 'api';

    public function __construct(private readonly UserService $users) {}

    /**
     * Self-service registration: creates an unverified couple account and
     * emails a verification link. The user cannot sign in until it is used.
     *
     * @param  array{name: string, email: string, password: string}  $attributes
     * @return array{user: User}
     */
    public function register(array $attributes): array
    {
        $user = $this->users->create([
            'name' => $attributes['name'],
            'email' => $attributes['email'],
            'password' => $attributes['password'],
            'is_active' => true,
        ], [RoleKey::Couple->value]);

        $user->sendEmailVerificationNotification();

        return [
            'user' => $user->load('roles.permissions'),
        ];
    }

    /**
     * Attempt to authenticate and issue a fresh Sanctum token.
     *
     * @return array{user: User, token: string}
     *
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function login(string $email, string $password, ?string $deviceName = null, ?string $portal = null): array
    {
        $user = User::query()
            ->where('email', $email)
            ->with('roles.permissions')
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthenticationException('Invalid email or password.');
        }

        if (! $user->is_active) {
            throw new AuthenticationException('This account has been deactivated.');
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Please verify your email address before logging in.'],
            ]);
        }

        $this->assertPortalAccess($user, $portal);

        return [
            'user' => $user,
            'token' => $this->issueToken($user, $deviceName),
        ];
    }

    /**
     * Enforce which app "portal" a user may sign in to. The couple portal
     * (sls-client) is for couples only — platform admins and organizers must
     * use the admin portal. An absent/unknown portal stays unrestricted so the
     * shared login endpoint remains backward compatible.
     *
     * @throws AuthorizationException
     */
    private function assertPortalAccess(User $user, ?string $portal): void
    {
        /** @var list<RoleKey>|null $allowedRoles */
        $allowedRoles = match ($portal) {
            'couple' => [RoleKey::Couple],
            'admin' => [RoleKey::SuperAdmin, RoleKey::Organizer],
            default => null,
        };

        if ($allowedRoles === null) {
            return;
        }

        foreach ($allowedRoles as $role) {
            if ($user->hasRole($role)) {
                return;
            }
        }

        throw new AuthorizationException(
            $portal === 'couple'
                ? 'This is the couple portal. Admin accounts must sign in through the admin portal.'
                : 'Your account is not permitted to access this portal.'
        );
    }

    /**
     * Rotate the caller's current token (refresh strategy: short-lived
     * tokens re-issued on demand; the old token is revoked atomically).
     *
     * @return array{user: User, token: string}
     */
    public function refresh(User $user, ?string $deviceName = null): array
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return [
            'user' => $user->load('roles.permissions'),
            'token' => $this->issueToken($user, $deviceName),
        ];
    }

    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    public function logoutAllDevices(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateProfile(User $user, array $attributes): User
    {
        $user->fill($attributes)->save();

        return $user->load('roles.permissions');
    }

    public function changePassword(User $user, string $newPassword): void
    {
        $user->forceFill(['password' => $newPassword])->save();

        // Revoke every other session; the current token stays valid.
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentTokenId, fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();
    }

    /** Send a verification link without revealing whether an address is registered. */
    public function resendEmailVerification(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * @throws ValidationException
     */
    public function sendPasswordResetLink(string $email): void
    {
        try {
            $status = Password::sendResetLink(['email' => $email]);
        } catch (Throwable $e) {
            // The mail provider rejected/failed the send (e.g. an unverified
            // recipient or sender). Don't surface a 500 — log it and return the
            // same generic response (also avoids email enumeration).
            Log::warning('Password reset email failed to send', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($status === Password::INVALID_USER) {
            // Surface a clear warning so the user knows no account exists yet.
            // NOTE: this intentionally allows email enumeration in exchange for
            // clearer UX (product decision).
            throw ValidationException::withMessages([
                'email' => ['No account exists for this email yet. Please sign up first.'],
            ]);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }
    }

    /**
     * @param  array{token: string, email: string, password: string}  $credentials
     *
     * @throws ValidationException
     */
    public function resetPassword(array $credentials): void
    {
        $status = Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }
    }

    private function issueToken(User $user, ?string $deviceName): string
    {
        $abilities = $user->roleKeys() ?: ['*'];

        return $user
            ->createToken($deviceName ?: self::TOKEN_NAME_FALLBACK, $abilities)
            ->plainTextToken;
    }
}
