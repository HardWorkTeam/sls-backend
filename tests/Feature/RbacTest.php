<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/weddings')->assertStatus(401);
    }

    public function test_couple_cannot_access_admin_users(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleKey::Couple));

        $this->getJson('/api/admin/users')->assertStatus(403);
    }

    public function test_organizer_cannot_access_admin_users(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleKey::Organizer));

        $this->getJson('/api/admin/users')->assertStatus(403);
    }

    public function test_super_admin_can_access_admin_users(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleKey::SuperAdmin));

        $this->getJson('/api/admin/users')->assertOk();
    }
}
