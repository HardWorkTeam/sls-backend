<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Models\Guest;
use App\Models\Wedding;
use App\Models\WeddingTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithPlans;
use Tests\Concerns\InteractsWithRoles;
use Tests\TestCase;

class SeatingTest extends TestCase
{
    use InteractsWithPlans, InteractsWithRoles, RefreshDatabase;

    /** Authenticate a couple and return their seating-enabled wedding. */
    private function seatingWedding(): Wedding
    {
        $owner = $this->userWithRole(RoleKey::Couple);
        $wedding = $this->paidWeddingFor($owner, modules: ['seating']);
        Sanctum::actingAs($owner);

        return $wedding;
    }

    private function table(Wedding $wedding, string $name, int $capacity): WeddingTable
    {
        return WeddingTable::query()->create([
            'wedding_id' => $wedding->id,
            'table_name' => $name,
            'capacity' => $capacity,
        ]);
    }

    public function test_can_create_a_table(): void
    {
        $wedding = $this->seatingWedding();

        $this->postJson("/api/weddings/{$wedding->id}/tables", [
            'table_name' => 'Head Table',
            'capacity' => 8,
        ])->assertCreated();

        $this->assertDatabaseHas('wedding_tables', [
            'wedding_id' => $wedding->id,
            'table_name' => 'Head Table',
            'capacity' => 8,
        ]);
    }

    public function test_can_assign_a_guest_to_a_table(): void
    {
        $wedding = $this->seatingWedding();
        $table = $this->table($wedding, 'T1', 4);
        $guest = Guest::factory()->create(['wedding_id' => $wedding->id]);

        $this->postJson("/api/weddings/{$wedding->id}/seatings/assign", [
            'guest_id' => $guest->id,
            'wedding_table_id' => $table->id,
        ])->assertOk();

        $this->assertDatabaseHas('guest_seatings', [
            'wedding_id' => $wedding->id,
            'guest_id' => $guest->id,
            'wedding_table_id' => $table->id,
        ]);
    }

    public function test_cannot_assign_to_a_full_table(): void
    {
        $wedding = $this->seatingWedding();
        $table = $this->table($wedding, 'Tiny', 1);
        $first = Guest::factory()->create(['wedding_id' => $wedding->id]);
        $second = Guest::factory()->create(['wedding_id' => $wedding->id]);

        $this->postJson("/api/weddings/{$wedding->id}/seatings/assign", [
            'guest_id' => $first->id,
            'wedding_table_id' => $table->id,
        ])->assertOk();

        $this->postJson("/api/weddings/{$wedding->id}/seatings/assign", [
            'guest_id' => $second->id,
            'wedding_table_id' => $table->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('wedding_table_id');
    }

    public function test_can_unassign_a_guest(): void
    {
        $wedding = $this->seatingWedding();
        $table = $this->table($wedding, 'T1', 4);
        $guest = Guest::factory()->create(['wedding_id' => $wedding->id]);

        $this->postJson("/api/weddings/{$wedding->id}/seatings/assign", [
            'guest_id' => $guest->id,
            'wedding_table_id' => $table->id,
        ])->assertOk();

        $this->postJson("/api/weddings/{$wedding->id}/seatings/unassign", [
            'guest_id' => $guest->id,
        ])->assertOk();

        $this->assertDatabaseMissing('guest_seatings', ['guest_id' => $guest->id]);
    }

    public function test_auto_seat_assigns_all_unseated_guests(): void
    {
        $wedding = $this->seatingWedding();
        $this->table($wedding, 'T1', 10);
        Guest::factory()->count(5)->create(['wedding_id' => $wedding->id]);

        $this->postJson("/api/weddings/{$wedding->id}/seatings/auto")
            ->assertOk()
            ->assertJsonPath('data.seated', 5)
            ->assertJsonPath('data.unseated', 0);

        $this->assertDatabaseCount('guest_seatings', 5);
    }

    public function test_report_summarizes_capacity_and_seating(): void
    {
        $wedding = $this->seatingWedding();
        $table = $this->table($wedding, 'T1', 10);
        Guest::factory()->count(2)->create(['wedding_id' => $wedding->id]); // left unseated
        $seated = Guest::factory()->create(['wedding_id' => $wedding->id]);

        $this->postJson("/api/weddings/{$wedding->id}/seatings/assign", [
            'guest_id' => $seated->id,
            'wedding_table_id' => $table->id,
        ])->assertOk();

        $this->getJson("/api/weddings/{$wedding->id}/seatings/report")
            ->assertOk()
            ->assertJsonPath('data.total_capacity', 10)
            ->assertJsonPath('data.total_seated', 1)
            ->assertJsonPath('data.unseated_guests', 2);
    }
}
