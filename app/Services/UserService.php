<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(private readonly UserRepository $users) {}

    public function list(?string $search, ?string $roleKey, int $perPage = 15): LengthAwarePaginator
    {
        return $this->users->search($search, $roleKey, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $roleKeys
     */
    public function create(array $attributes, array $roleKeys): User
    {
        /** @var User $user */
        $user = $this->users->create($attributes);
        $this->syncRoles($user, $roleKeys);

        return $user->load('roles');
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>|null  $roleKeys
     */
    public function update(User $user, array $attributes, ?array $roleKeys = null): User
    {
        if ($attributes !== []) {
            $this->users->update($user, $attributes);
        }

        if ($roleKeys !== null) {
            $this->syncRoles($user, $roleKeys);
        }

        return $user->load('roles');
    }

    public function delete(User $user): void
    {
        $user->tokens()->delete();
        $this->users->delete($user);
    }

    /**
     * @param  list<string>  $roleKeys
     */
    private function syncRoles(User $user, array $roleKeys): void
    {
        $roleIds = Role::query()->whereIn('key', $roleKeys)->pluck('id');
        $user->roles()->sync($roleIds);
    }
}
