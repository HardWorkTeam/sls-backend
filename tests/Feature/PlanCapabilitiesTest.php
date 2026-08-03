<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wedding;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `PlanCapabilities::forWedding()` decides what a wedding may do. It resolves
 * the plan through the `paidSubscription` relation, so these tests cover both
 * halves of that: it must still pick the latest PAID subscription (never a
 * pending upgrade), and repeated checks within one request must not re-query.
 */
class PlanCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    private Wedding $wedding;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['is_active' => true]);
        $this->wedding = Wedding::factory()->create(['created_by_user_id' => $user->id]);
    }

    private function package(string $name, bool $seating, ?int $guestLimit): Package
    {
        return Package::create([
            'name' => $name,
            'price' => 100,
            'currency' => 'USD',
            'is_active' => true,
            'capabilities' => (new PlanCapabilities(
                seating: $seating,
                gallery: false,
                gifts: false,
                expense: false,
                rsvp: false,
                timeline: false,
                checkin: false,
                guestLimit: $guestLimit,
                invitationDesignLimit: null,
            ))->toArray(),
        ]);
    }

    private function subscribe(Package $package, SubscriptionStatus $status): Subscription
    {
        return Subscription::create([
            'wedding_id' => $this->wedding->id,
            'package_id' => $package->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => $status->value,
            'paid_at' => $status === SubscriptionStatus::Paid ? now() : null,
        ]);
    }

    public function test_a_wedding_without_a_paid_plan_gets_the_base_allowance(): void
    {
        $this->subscribe($this->package('Premium', seating: true, guestLimit: null), SubscriptionStatus::Pending);

        $capabilities = PlanCapabilities::forWedding($this->wedding);

        $this->assertFalse($capabilities->seating);
        $this->assertSame(0, $capabilities->guestLimit);
    }

    public function test_capabilities_come_from_the_paid_plan_not_a_pending_upgrade(): void
    {
        $paid = $this->package('Standard', seating: true, guestLimit: 150);
        $this->subscribe($paid, SubscriptionStatus::Paid);

        // A later, still-unpaid upgrade must not unlock anything.
        $this->subscribe($this->package('Premium', seating: true, guestLimit: null), SubscriptionStatus::Pending);

        $capabilities = PlanCapabilities::forWedding($this->wedding->fresh());

        $this->assertTrue($capabilities->seating);
        $this->assertSame(150, $capabilities->guestLimit);
    }

    public function test_the_most_recent_paid_plan_wins(): void
    {
        $this->subscribe($this->package('Standard', seating: false, guestLimit: 150), SubscriptionStatus::Paid);
        $this->subscribe($this->package('Premium', seating: true, guestLimit: null), SubscriptionStatus::Paid);

        $capabilities = PlanCapabilities::forWedding($this->wedding->fresh());

        $this->assertTrue($capabilities->seating);
        $this->assertNull($capabilities->guestLimit);
    }

    public function test_repeated_checks_on_one_wedding_do_not_re_query(): void
    {
        $this->subscribe($this->package('Premium', seating: true, guestLimit: null), SubscriptionStatus::Paid);

        $wedding = $this->wedding->fresh();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        // Mirrors one real request: the plan.module middleware checks the plan,
        // then the service checks it again while enforcing the guest cap.
        PlanCapabilities::forWedding($wedding);
        $first = $queries;

        PlanCapabilities::forWedding($wedding);
        PlanCapabilities::forWedding($wedding);

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first, $queries, 'Repeat capability checks should reuse the loaded relation.');
    }
}
