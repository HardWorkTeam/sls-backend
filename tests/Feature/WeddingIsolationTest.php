<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WeddingIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $coupleRole = Role::create([
            'key' => RoleKey::Couple->value,
            'name' => 'Couple',
        ]);

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->otherUser = User::factory()->create(['is_active' => true]);
        $this->owner->roles()->attach($coupleRole);
        $this->otherUser->roles()->attach($coupleRole);

        Sanctum::actingAs($this->owner);
    }

    public function test_a_couple_cannot_view_another_couples_wedding(): void
    {
        $foreignWedding = Wedding::factory()->create([
            'created_by_user_id' => $this->otherUser->id,
        ]);

        $this->getJson("/api/weddings/{$foreignWedding->id}")
            ->assertNotFound();
    }

    public function test_a_nested_invitation_must_belong_to_the_authorized_wedding(): void
    {
        $ownedWedding = Wedding::factory()->create([
            'created_by_user_id' => $this->owner->id,
        ]);
        $foreignWedding = Wedding::factory()->create([
            'created_by_user_id' => $this->otherUser->id,
        ]);
        $foreignInvitation = Invitation::factory()->create([
            'wedding_id' => $foreignWedding->id,
        ]);

        $this->getJson(
            "/api/weddings/{$ownedWedding->id}/invitations/{$foreignInvitation->id}",
        )->assertNotFound();
    }

    public function test_a_couple_can_view_an_invitation_from_their_own_wedding(): void
    {
        $ownedWedding = Wedding::factory()->create([
            'created_by_user_id' => $this->owner->id,
        ]);
        $invitation = Invitation::factory()->create([
            'wedding_id' => $ownedWedding->id,
        ]);

        $this->getJson("/api/weddings/{$ownedWedding->id}/invitations/{$invitation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invitation->id);
    }
}
