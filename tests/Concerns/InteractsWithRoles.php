<?php

namespace Tests\Concerns;

use App\Enums\RoleKey;
use App\Models\Role;
use App\Models\User;

trait InteractsWithRoles
{
    /**
     * Create a user and attach the given role key, creating the role if needed.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(RoleKey|string $role, array $attributes = []): User
    {
        $key = $role instanceof RoleKey ? $role->value : $role;

        $user = User::factory()->create($attributes);

        $roleModel = Role::query()->firstOrCreate(['key' => $key], ['name' => $key]);
        $user->roles()->attach($roleModel);

        return $user->fresh();
    }
}
