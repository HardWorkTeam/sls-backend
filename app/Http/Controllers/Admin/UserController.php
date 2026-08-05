<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection(
            $this->userService->list(
                $request->query('search'),
                $request->query('role'),
                (int) $request->query('per_page', '15'),
            ),
        );
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $roles = $data['roles'];
        unset($data['roles']);

        $user = $this->userService->create($data, $roles);
        $user->markEmailAsVerified();

        return UserResource::make($user)->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return UserResource::make($user->load('roles.permissions'));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        $roles = $data['roles'] ?? null;
        unset($data['roles']);

        if (array_key_exists('password', $data) && $data['password'] === null) {
            unset($data['password']);
        }

        return UserResource::make($this->userService->update($user, $data, $roles));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($request->user()->id === $user->id, 422, 'You cannot delete your own account.');

        $this->userService->delete($user);

        return response()->json(['message' => 'User deleted.']);
    }

    public function roles(): AnonymousResourceCollection
    {
        return RoleResource::collection(Role::query()->with('permissions')->get());
    }
}
