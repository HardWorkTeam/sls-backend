<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\Role;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WeddingDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Wedding $wedding;

    protected function setUp(): void
    {
        parent::setUp();

        $coupleRole = Role::create([
            'key' => RoleKey::Couple->value,
            'name' => 'Couple',
        ]);

        $this->owner = User::factory()->create(['is_active' => true]);
        $this->owner->roles()->attach($coupleRole);

        $this->wedding = Wedding::factory()->create([
            'created_by_user_id' => $this->owner->id,
        ]);

        Sanctum::actingAs($this->owner);
    }

    public function test_wedding_dashboard_includes_other_group_for_ungrouped_guests(): void
    {
        $group = GuestGroup::create([
            'wedding_id' => $this->wedding->id,
            'name' => 'Family',
            'type' => 'family',
        ]);

        Guest::create([
            'wedding_id' => $this->wedding->id,
            'guest_group_id' => $group->id,
            'name' => 'Grouped Guest',
        ]);

        Guest::create([
            'wedding_id' => $this->wedding->id,
            'guest_group_id' => null,
            'name' => 'Ungrouped Guest',
        ]);

        $response = $this->getJson("/api/weddings/{$this->wedding->id}/dashboard")
            ->assertOk();

        $guestsByGroup = $response->json('data.guests_by_group');

        $this->assertCount(2, $guestsByGroup);

        $this->assertEquals([
            [
                'group' => 'Family',
                'type' => 'family',
                'total' => 1,
            ],
            [
                'group' => 'Other',
                'type' => 'custom',
                'total' => 1,
            ],
        ], $guestsByGroup);
    }

    public function test_wedding_dashboard_does_not_include_other_group_if_no_ungrouped_guests(): void
    {
        $group = GuestGroup::create([
            'wedding_id' => $this->wedding->id,
            'name' => 'Family',
            'type' => 'family',
        ]);

        Guest::create([
            'wedding_id' => $this->wedding->id,
            'guest_group_id' => $group->id,
            'name' => 'Grouped Guest',
        ]);

        $response = $this->getJson("/api/weddings/{$this->wedding->id}/dashboard")
            ->assertOk();

        $guestsByGroup = $response->json('data.guests_by_group');

        $this->assertCount(1, $guestsByGroup);
        $this->assertEquals([
            [
                'group' => 'Family',
                'type' => 'family',
                'total' => 1,
            ],
        ], $guestsByGroup);
    }
}
