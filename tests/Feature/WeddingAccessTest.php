<?php

namespace Tests\Feature;

use App\Enums\MemberRole;
use App\Enums\RoleKey;
use App\Models\Wedding;
use App\Models\WeddingMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class WeddingAccessTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    private function validWeddingPayload(): array
    {
        return [
            'wedding_name' => 'Alice & Bob',
            'bride_name' => 'Alice',
            'groom_name' => 'Bob',
        ];
    }

    public function test_couple_can_create_a_wedding(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleKey::Couple));

        $this->postJson('/api/weddings', $this->validWeddingPayload())
            ->assertCreated();
    }

    public function test_organizer_cannot_create_a_wedding(): void
    {
        // POST /weddings is restricted to the couple role.
        Sanctum::actingAs($this->userWithRole(RoleKey::Organizer));

        $this->postJson('/api/weddings', $this->validWeddingPayload())
            ->assertStatus(403);
    }

    public function test_creating_a_wedding_requires_required_fields(): void
    {
        Sanctum::actingAs($this->userWithRole(RoleKey::Couple));

        $this->postJson('/api/weddings', ['wedding_name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['wedding_name', 'bride_name', 'groom_name']);
    }

    public function test_creator_can_view_their_own_wedding(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/weddings/{$wedding->id}")->assertOk();
    }

    public function test_couple_cannot_view_another_couples_wedding(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);

        // A different couple, not a member, is hidden the wedding entirely (404).
        Sanctum::actingAs($this->userWithRole(RoleKey::Couple));

        $this->getJson("/api/weddings/{$wedding->id}")->assertStatus(404);
    }

    public function test_super_admin_can_view_any_wedding(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);

        Sanctum::actingAs($this->userWithRole(RoleKey::SuperAdmin));

        $this->getJson("/api/weddings/{$wedding->id}")->assertOk();
    }

    public function test_added_member_can_view_the_wedding(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);

        $member = $this->userWithRole(RoleKey::Couple);
        WeddingMember::create([
            'wedding_id' => $wedding->id,
            'user_id' => $member->id,
            'member_role' => MemberRole::Bride->value,
        ]);

        Sanctum::actingAs($member);

        $this->getJson("/api/weddings/{$wedding->id}")->assertOk();
    }
}
