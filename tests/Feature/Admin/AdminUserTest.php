<?php

namespace Tests\Feature\Admin;

use App\Enums\RoleKey;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The store endpoint validates roles against the roles table.
        $this->seed(RolePermissionSeeder::class);
    }

    private function actingAsSuperAdmin(): User
    {
        $admin = $this->userWithRole(RoleKey::SuperAdmin);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_super_admin_can_list_users(): void
    {
        $this->actingAsSuperAdmin();
        User::factory()->count(2)->create();

        $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_super_admin_can_create_a_user_with_roles(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/users', [
            'name' => 'New Organizer',
            'email' => 'neworg@example.com',
            'password' => 'a-strong-password',
            'roles' => [RoleKey::Organizer->value],
        ])->assertCreated();

        $user = User::query()->where('email', 'neworg@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole(RoleKey::Organizer));
    }

    public function test_creating_a_user_requires_at_least_one_role(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/admin/users', [
            'name' => 'No Role',
            'email' => 'norole@example.com',
            'password' => 'a-strong-password',
            'roles' => [],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('roles');
    }

    public function test_super_admin_can_update_a_user(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'roles' => [RoleKey::Couple->value],
        ])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
        $this->assertTrue($user->fresh()->hasRole(RoleKey::Couple));
    }

    public function test_super_admin_can_delete_another_user(): void
    {
        $this->actingAsSuperAdmin();
        $user = User::factory()->create();

        $this->deleteJson("/api/admin/users/{$user->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_super_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->actingAsSuperAdmin();

        $this->deleteJson("/api/admin/users/{$admin->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_roles_endpoint_lists_available_roles(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson('/api/admin/roles')
            ->assertOk()
            ->assertJsonFragment(['key' => RoleKey::Organizer->value]);
    }
}
