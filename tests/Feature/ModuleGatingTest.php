<?php

namespace Tests\Feature;

use App\Enums\GiftType;
use App\Enums\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithPlans;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class ModuleGatingTest extends TestCase
{
    use InteractsWithPlans, InteractsWithRoles, RefreshDatabase;

    public function test_gifts_module_is_blocked_when_plan_excludes_it(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        // Paid plan, but the gifts module is NOT enabled.
        $wedding = $this->paidWeddingFor($owner, modules: ['expense']);

        Sanctum::actingAs($owner);

        $this->getJson("/api/weddings/{$wedding->id}/gifts")->assertStatus(403);
    }

    public function test_gifts_module_is_accessible_when_plan_includes_it(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner, modules: ['gifts']);

        Sanctum::actingAs($owner);

        $this->getJson("/api/weddings/{$wedding->id}/gifts")->assertOk();

        $this->postJson("/api/weddings/{$wedding->id}/gifts", [
            'gift_type' => GiftType::Cash->value,
            'amount' => 50,
        ])->assertCreated();

        $this->assertDatabaseHas('gifts', [
            'wedding_id' => $wedding->id,
            'gift_type' => GiftType::Cash->value,
        ]);
    }

    public function test_expense_module_is_blocked_when_plan_excludes_it(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner, modules: ['gifts']);

        Sanctum::actingAs($owner);

        $this->getJson("/api/weddings/{$wedding->id}/expenses")->assertStatus(403);
    }

    public function test_expense_module_is_accessible_when_plan_includes_it(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner, modules: ['expense']);

        Sanctum::actingAs($owner);

        $this->postJson("/api/weddings/{$wedding->id}/expenses", [
            'item_name' => 'Catering deposit',
            'amount' => 500,
        ])->assertCreated();

        $this->assertDatabaseHas('expenses', [
            'wedding_id' => $wedding->id,
            'item_name' => 'Catering deposit',
        ]);
    }

    public function test_seating_module_is_blocked_when_plan_excludes_it(): void
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner, modules: ['gifts']);

        Sanctum::actingAs($owner);

        $this->getJson("/api/weddings/{$wedding->id}/tables")->assertStatus(403);
    }
}
