<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Enums\RsvpStatus;
use App\Enums\WeddingStatus;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\RsvpResponse;
use App\Models\User;
use App\Models\Wedding;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `DashboardService::overview()` scopes every figure to the weddings a user can
 * see. It builds that scope as a SQL subquery rather than materialising the id
 * list in PHP, so these tests pin the numbers down — the totals must stay
 * identical, and a foreign wedding's data must never leak into them.
 */
class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private Wedding $ownedWedding;

    protected function setUp(): void
    {
        parent::setUp();

        $organizerRole = Role::create(['key' => RoleKey::Organizer->value, 'name' => 'Organizer']);

        $this->organizer = User::factory()->create(['is_active' => true]);
        $this->organizer->roles()->attach($organizerRole);

        $otherUser = User::factory()->create(['is_active' => true]);

        $this->ownedWedding = Wedding::factory()->create([
            'created_by_user_id' => $this->organizer->id,
            'status' => WeddingStatus::Draft->value,
        ]);
        $foreignWedding = Wedding::factory()->create([
            'created_by_user_id' => $otherUser->id,
            'status' => WeddingStatus::Draft->value,
        ]);

        $this->seedWedding($this->ownedWedding, guests: 3, accepted: 2, declined: 1);
        $this->seedWedding($foreignWedding, guests: 7, accepted: 5, declined: 2);
    }

    private function seedWedding(Wedding $wedding, int $guests, int $accepted, int $declined): void
    {
        $invitation = Invitation::factory()->create(['wedding_id' => $wedding->id]);

        for ($i = 0; $i < $guests; $i++) {
            Guest::create(['wedding_id' => $wedding->id, 'name' => "Guest {$i}"]);
        }

        foreach ([RsvpStatus::Accepted->value => $accepted, RsvpStatus::Declined->value => $declined] as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                RsvpResponse::create([
                    'wedding_id' => $wedding->id,
                    'invitation_id' => $invitation->id,
                    'guest_name' => "Responder {$status} {$i}",
                    'number_of_guests' => 1,
                    'status' => $status,
                    'responded_at' => now(),
                ]);
            }
        }
    }

    public function test_overview_counts_only_the_users_own_weddings(): void
    {
        $overview = app(DashboardService::class)->overview($this->organizer);

        $this->assertSame(1, $overview['cards']['total_weddings']);
        $this->assertSame(3, $overview['cards']['total_guests']);
        $this->assertSame(3, $overview['cards']['total_rsvp']);
        // 2 of 3 responses accepted.
        $this->assertSame(66.7, $overview['cards']['attendance_rate']);
    }

    public function test_overview_charts_stay_scoped_to_visible_weddings(): void
    {
        $overview = app(DashboardService::class)->overview($this->organizer);

        $this->assertSame(1, $overview['charts']['wedding_status'][WeddingStatus::Draft->value]);
        $this->assertSame(3, (int) $overview['charts']['guest_distribution']->sum());
        $this->assertSame(3, (int) collect($overview['charts']['rsvp_trend'])->sum('total'));
    }

    public function test_a_user_with_no_weddings_gets_zeroed_cards(): void
    {
        $stranger = User::factory()->create(['is_active' => true]);

        $overview = app(DashboardService::class)->overview($stranger);

        $this->assertSame(0, $overview['cards']['total_weddings']);
        $this->assertSame(0, $overview['cards']['total_guests']);
        $this->assertSame(0, $overview['cards']['total_rsvp']);
        $this->assertSame(0.0, $overview['cards']['attendance_rate']);
    }

    public function test_overview_does_not_grow_its_query_count_with_the_number_of_weddings(): void
    {
        // Warm the user's `roles` relation first — `visibleTo()` calls
        // `hasRole()`, which lazy-loads it once per User instance and would
        // otherwise show up only in the first measurement.
        app(DashboardService::class)->overview($this->organizer);

        $baseline = $this->countQueriesForOverview();

        for ($i = 0; $i < 5; $i++) {
            $wedding = Wedding::factory()->create([
                'created_by_user_id' => $this->organizer->id,
                'status' => WeddingStatus::Draft->value,
            ]);
            $this->seedWedding($wedding, guests: 2, accepted: 1, declined: 1);
        }

        $this->assertSame($baseline, $this->countQueriesForOverview());
    }

    private function countQueriesForOverview(): int
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        app(DashboardService::class)->overview($this->organizer);

        return $queries;
    }
}
