<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
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
            'message' => 'If that email exists, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->authService->resetPassword($request->only('token', 'email', 'password', 'password_confirmation'));

        return response()->json(['message' => 'Password has been reset.']);
    }
}
