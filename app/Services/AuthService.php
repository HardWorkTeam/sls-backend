<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const TOKEN_NAME_FALLBACK = 'api';

    /**
     * Attempt to authenticate and issue a fresh Sanctum token.
     *
     * @return array{user: User, token: string}
     *
     * @throws AuthenticationException
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
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

        return [
            'user' => $user,
            'token' => $this->issueToken($user, $deviceName),
        ];
    }

    /**
     * Rotate the caller's current token (refresh strategy: short-lived
     * tokens re-issued on demand; the old token is revoked atomically).
     *
     * @return array{user: User, token: string}
     */
    public function refresh(User $user, ?string $deviceName = null): array
    {
        $user->currentAccessToken()?->delete();

        return [
            'user' => $user->load('roles.permissions'),
            'token' => $this->issueToken($user, $deviceName),
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
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

    /**
     * @throws ValidationException
     */
    public function sendPasswordResetLink(string $email): void
    {
        $status = Password::sendResetLink(['email' => $email]);

        if ($status !== Password::RESET_LINK_SENT && $status !== Password::INVALID_USER) {
            // INVALID_USER intentionally returns success to avoid email enumeration.
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
