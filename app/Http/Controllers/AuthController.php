<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'message' => 'Account created. Please check your email to verify your address before logging in.',
            'user' => UserResource::make($result['user']),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
            $request->validated('portal'),
        );

        return response()->json([
            'message' => 'Login successful.',
            'token' => $result['token'],
            'user' => UserResource::make($result['user']),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $result = $this->authService->refresh(
            $request->user(),
            $request->input('device_name'),
        );

        return response()->json([
            'message' => 'Token refreshed.',
            'token' => $result['token'],
            'user' => UserResource::make($result['user']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json(['message' => 'Logged out.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->authService->logoutAllDevices($request->user());

        return response()->json(['message' => 'Logged out from all devices.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => UserResource::make($request->user()->load('roles.permissions')),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile($request->user(), $request->validated());

        return response()->json([
            'message' => 'Profile updated.',
            'user' => UserResource::make($user),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword($request->user(), $request->validated('password'));

        return response()->json(['message' => 'Password changed.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetLink($request->validated('email'));

        return response()->json([
            'message' => 'A password reset link has been sent to your email.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->only('token', 'email', 'password', 'password_confirmation'));

        return response()->json(['message' => 'Password has been reset.']);
    }

    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect()->to(rtrim((string) config('services.client.url'), '/').'/verify-email?verified=1');
    }

    public function resendEmailVerification(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $this->authService->resendEmailVerification($request->string('email')->toString());

        return response()->json([
            'message' => 'If this address needs verification, a new link has been sent.',
        ]);
    }
}
