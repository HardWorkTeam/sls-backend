<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Models\Guest;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithPlans;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class GuestTest extends TestCase
{
    use InteractsWithPlans, InteractsWithRoles, RefreshDatabase;

    public function test_guest_creation_is_blocked_without_a_paid_plan(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);

        Sanctum::actingAs($owner);

        // Base allowance has guest_limit = 0 → adding any guest is gated.
        $this->postJson("/api/weddings/{$wedding->id}/guests", ['name' => 'Uncle Bob'])
            ->assertStatus(422);
    }

    public function test_couple_can_add_a_guest_with_a_paid_plan(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner);

        Sanctum::actingAs($owner);

        $this->postJson("/api/weddings/{$wedding->id}/guests", [
            'name' => 'Uncle Bob',
            'phone' => '012345678',
        ])->assertCreated();

        $this->assertDatabaseHas('guests', [
            'wedding_id' => $wedding->id,
            'name' => 'Uncle Bob',
        ]);
    }

    public function test_guest_limit_is_enforced(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner, guestLimit: 1);
        Guest::factory()->create(['wedding_id' => $wedding->id]);

        Sanctum::actingAs($owner);

        // One guest already exists and the cap is 1 → the next one is rejected.
        $this->postJson("/api/weddings/{$wedding->id}/guests", ['name' => 'One Too Many'])
            ->assertStatus(422);
    }

    public function test_can_list_guests_for_a_wedding(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner);
        Guest::factory()->count(3)->create(['wedding_id' => $wedding->id]);

        Sanctum::actingAs($owner);

        $this->getJson("/api/weddings/{$wedding->id}/guests")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_update_a_guest(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner);
        $guest = Guest::factory()->create(['wedding_id' => $wedding->id, 'name' => 'Old Name']);

        Sanctum::actingAs($owner);

        $this->putJson("/api/weddings/{$wedding->id}/guests/{$guest->id}", ['name' => 'New Name'])
            ->assertOk();

        $this->assertDatabaseHas('guests', ['id' => $guest->id, 'name' => 'New Name']);
    }

    public function test_cannot_touch_a_guest_from_another_wedding(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner);
        $otherWedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);
        $foreignGuest = Guest::factory()->create(['wedding_id' => $otherWedding->id]);

        Sanctum::actingAs($owner);

        // {guest} does not belong to {wedding} → 404 even though the owner can
        // access both weddings.
        $this->putJson("/api/weddings/{$wedding->id}/guests/{$foreignGuest->id}", ['name' => 'X'])
            ->assertStatus(404);
    }

    public function test_can_delete_a_guest(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner);
        $guest = Guest::factory()->create(['wedding_id' => $wedding->id]);

        Sanctum::actingAs($owner);

        $this->deleteJson("/api/weddings/{$wedding->id}/guests/{$guest->id}")
            ->assertOk();

        $this->assertSoftDeleted('guests', ['id' => $guest->id]);
    }
}
