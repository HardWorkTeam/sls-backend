<?php

namespace Database\Seeders;

use App\Enums\RoleKey;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Role -> permission matrix. Wildcards are expanded per module action.
     */
    private const PERMISSIONS = [
        'weddings.view', 'weddings.manage',
        'users.view', 'users.manage',
        'packages.manage',
        'templates.manage',
        'invitations.view', 'invitations.manage',
        'guests.view', 'guests.manage',
        'rsvp.view', 'rsvp.manage',
        'seating.view', 'seating.manage',
        'gifts.view', 'gifts.manage',
        'timeline.view', 'timeline.manage',
        'gallery.view', 'gallery.manage',
        'announcements.view', 'announcements.manage',
        'reports.view',
    ];

    private const ROLE_MATRIX = [
        RoleKey::SuperAdmin->value => ['*'],
        RoleKey::Organizer->value => [
            'weddings.view', 'weddings.manage',
            'invitations.view', 'invitations.manage',
            'guests.view', 'guests.manage',
            'rsvp.view', 'rsvp.manage',
            'seating.view', 'seating.manage',
            'gifts.view', 'gifts.manage',
            'timeline.view', 'timeline.manage',
            'gallery.view', 'gallery.manage',
            'announcements.view', 'announcements.manage',
            'reports.view',
        ],
        RoleKey::Couple->value => [
            'weddings.view',
            'invitations.view',
            'guests.view', 'guests.manage',
            'rsvp.view',
            'seating.view', 'seating.manage',
            'gifts.view', 'gifts.manage',
            'timeline.view',
            'gallery.view', 'gallery.manage',
            'announcements.view',
        ],
    ];

    private const ROLE_NAMES = [
        RoleKey::SuperAdmin->value => 'Super Admin',
        RoleKey::Organizer->value => 'Wedding Organizer',
        RoleKey::Couple->value => 'Bride / Groom',
    ];

    public function run(): void
    {
        $permissions = collect(self::PERMISSIONS)->mapWithKeys(function (string $key) {
            $permission = Permission::query()->firstOrCreate(
                ['key' => $key],
                ['description' => ucfirst(str_replace(['.', '_'], ' ', $key))],
            );

            return [$key => $permission->id];
        });

        foreach (self::ROLE_MATRIX as $roleKey => $permissionKeys) {
            $role = Role::query()->firstOrCreate(
                ['key' => $roleKey],
                ['name' => self::ROLE_NAMES[$roleKey]],
            );

            $ids = $permissionKeys === ['*']
                ? $permissions->values()
                : $permissions->only($permissionKeys)->values();

            $role->permissions()->sync($ids);
        }
    }
}
