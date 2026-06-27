<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Enums\RoleKey;
use App\Enums\RsvpStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invitation;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class InvitationFlowTest extends TestCase
{
    use InteractsWithRoles, RefreshDatabase;

    /**
     * A wedding with a PAID subscription on an all-modules, unlimited-design
     * package — the only state in which invitation creation is allowed.
     */
    private function paidWeddingOwnedBy(User $owner): Wedding
    {
        $package = Package::query()->create([
            'name' => 'Test Premium',
            'price' => 100,
            'currency' => 'USD',
            'features' => ['All modules'],
            'capabilities' => [
                'modules' => [
                    'seating' => true, 'gallery' => true, 'gifts' => true,
                    'expense' => true, 'rsvp' => true, 'timeline' => true,
                ],
                'guest_limit' => null,
                'invitation_design_limit' => null,
            ],
            'is_active' => true,
        ]);

        $wedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);

        Subscription::query()->create([
            'wedding_id' => $wedding->id,
            'package_id' => $package->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => SubscriptionStatus::Paid->value,
            'paid_at' => now(),
        ]);

        return $wedding;
    }

    public function test_invitation_creation_is_blocked_without_a_paid_plan(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);

        Sanctum::actingAs($owner);

        // Base allowance has invitation_design_limit = 0 → gated.
        $this->postJson("/api/weddings/{$wedding->id}/invitations", ['title' => 'Our Day'])
            ->assertStatus(422);
    }

    public function test_couple_with_paid_plan_can_create_an_invitation(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingOwnedBy($owner);

        Sanctum::actingAs($owner);

        $this->postJson("/api/weddings/{$wedding->id}/invitations", ['title' => 'Our Day'])
            ->assertCreated();

        $this->assertDatabaseHas('invitations', [
            'wedding_id' => $wedding->id,
            'status' => InvitationStatus::Draft->value,
        ]);
    }

    public function test_creator_can_publish_an_invitation(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);
        $invitation = Invitation::factory()->create(['wedding_id' => $wedding->id]);

        Sanctum::actingAs($owner);

        $this->postJson("/api/weddings/{$wedding->id}/invitations/{$invitation->id}/publish")
            ->assertOk();

        $this->assertDatabaseHas('invitations', [
            'id' => $invitation->id,
            'status' => InvitationStatus::Published->value,
        ]);
    }

    public function test_public_can_view_a_published_invitation_by_code(): void
    {
        Invitation::factory()->published()->create(['invitation_code' => 'DEMOCODE']);

        $this->getJson('/api/public/invitations/DEMOCODE')->assertOk();
    }

    public function test_public_invitation_with_unknown_code_returns_404(): void
    {
        $this->getJson('/api/public/invitations/NOPE1234')->assertStatus(404);
    }

    public function test_guest_can_submit_rsvp_to_a_published_invitation(): void
    {
        $invitation = Invitation::factory()->published()->create(['invitation_code' => 'RSVPCODE']);

        $this->postJson('/api/public/invitations/RSVPCODE/rsvp', [
            'guest_name' => 'Jane Doe',
            'number_of_guests' => 2,
            'status' => RsvpStatus::Accepted->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.guest_name', 'Jane Doe');

        $this->assertDatabaseHas('rsvp_responses', [
            'invitation_id' => $invitation->id,
            'guest_name' => 'Jane Doe',
            'number_of_guests' => 2,
        ]);
    }

    public function test_guest_cannot_rsvp_to_an_unpublished_invitation(): void
    {
        // Draft invitation — only published ones accept public RSVPs.
        Invitation::factory()->create(['invitation_code' => 'DRAFTC0D']);

        $this->postJson('/api/public/invitations/DRAFTC0D/rsvp', [
            'guest_name' => 'Jane Doe',
            'number_of_guests' => 1,
            'status' => RsvpStatus::Accepted->value,
        ])->assertStatus(404);
    }

    public function test_rsvp_validation_requires_name_count_and_status(): void
    {
        Invitation::factory()->published()->create(['invitation_code' => 'VALIDCOD']);

        $this->postJson('/api/public/invitations/VALIDCOD/rsvp', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['guest_name', 'number_of_guests', 'status']);
    }
}
