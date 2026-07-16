<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => 'admin@srolanh.com'],
            [
                'name' => 'Srolanh Admin',
                'password' => 'srolanhadmin',
                'is_active' => true,
            ]
        );

        $roleId = Role::query()->where('key', RoleKey::SuperAdmin->value)->value('id');
        $user->roles()->syncWithoutDetaching([$roleId]);
    }
}
