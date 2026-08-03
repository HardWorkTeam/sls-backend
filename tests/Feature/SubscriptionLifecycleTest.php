<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wedding;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The package-payment lifecycle end to end:
 *
 *   select → (pay) → admin confirm/reject → (admin revert)
 *
 * This is the platform's money path and the only thing standing between a
 * couple and the paid feature set, so the tests below cover both the happy
 * path and every state guard the service applies. The guards matter more than
 * the happy path: each one exists because a stale admin screen or a couple
 * clicking twice would otherwise activate a plan nobody paid for, or discard a
 * payment claim that is mid-review.
 */
class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $couple;

    private User $admin;

    private Wedding $wedding;

    protected function setUp(): void
    {
        parent::setUp();

        // The submit step fires a best-effort Telegram alert. It already
        // no-ops when the bot token is unset, but faking the client keeps the
        // suite hermetic even if those env vars leak into a local run.
        Http::fake();

        $coupleRole = Role::create(['key' => RoleKey::Couple->value, 'name' => 'Couple']);
        $adminRole = Role::create(['key' => RoleKey::SuperAdmin->value, 'name' => 'Super Admin']);

        $this->couple = User::factory()->create(['is_active' => true]);
        $this->couple->roles()->attach($coupleRole);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->roles()->attach($adminRole);

        $this->wedding = Wedding::factory()->create([
            'created_by_user_id' => $this->couple->id,
        ]);
    }

    private function package(string $name, float $price, bool $seating = true): Package
    {
        return Package::create([
            'name' => $name,
            'price' => $price,
            'currency' => 'USD',
            'is_active' => true,
            'capabilities' => (new PlanCapabilities(
                seating: $seating,
                gallery: true,
                gifts: true,
                expense: true,
                rsvp: true,
                timeline: true,
                checkin: true,
                guestLimit: null,
                invitationDesignLimit: null,
            ))->toArray(),
        ]);
    }

    private function select(Package $package): TestResponse
    {
        return $this->postJson("/api/weddings/{$this->wedding->id}/subscription", [
            'package_id' => $package->id,
        ]);
    }

    private function pay(): TestResponse
    {
        return $this->postJson("/api/weddings/{$this->wedding->id}/subscription/pay", [
            'payment_method' => 'khqr',
            'payment_reference' => 'TXN-000123',
        ]);
    }

    // ---------------------------------------------------------------- select

    public function test_selecting_a_paid_package_starts_pending_and_does_not_unlock_anything(): void
    {
        Sanctum::actingAs($this->couple);
        $package = $this->package('Premium', 199.00);

        $this->select($package)
            ->assertCreated()
            ->assertJsonPath('data.status', SubscriptionStatus::Pending->value);

        $this->assertDatabaseHas('subscriptions', [
            'wedding_id' => $this->wedding->id,
            'package_id' => $package->id,
            'status' => SubscriptionStatus::Pending->value,
            'paid_at' => null,
        ]);

        // The plan is mirrored onto the wedding, but an unpaid plan grants nothing.
        $this->assertSame($package->id, $this->wedding->fresh()->package_id);
        $this->assertFalse(PlanCapabilities::forWedding($this->wedding->fresh())->seating);
    }

    public function test_selecting_a_free_package_activates_it_immediately(): void
    {
        Sanctum::actingAs($this->couple);
        $free = $this->package('Free', 0.0);

        $this->select($free)
            ->assertCreated()
            ->assertJsonPath('data.status', SubscriptionStatus::Paid->value);

        $this->assertNotNull(Subscription::firstWhere('package_id', $free->id)->paid_at);
        $this->assertTrue(PlanCapabilities::forWedding($this->wedding->fresh())->seating);
    }

    public function test_reselecting_while_a_payment_is_under_review_is_rejected(): void
    {
        Sanctum::actingAs($this->couple);
        $this->select($this->package('Premium', 199.00))->assertSuccessful();
        $this->pay()->assertOk();

        // Mutating the row here would wipe the payment reference and re-point
        // the couple's money at a different package mid-review.
        $this->select($this->package('Standard', 99.00))
            ->assertStatus(422);

        $this->assertSame(
            SubscriptionStatus::Submitted,
            Subscription::latest('id')->first()->status,
        );
        $this->assertSame('TXN-000123', Subscription::latest('id')->first()->payment_reference);
    }

    public function test_a_confirmed_paid_plan_locks_and_cannot_be_swapped(): void
    {
        Sanctum::actingAs($this->couple);
        $premium = $this->package('Premium', 199.00);
        $this->select($premium)->assertSuccessful();
        $this->pay()->assertOk();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/subscriptions/'.Subscription::latest('id')->first()->id.'/confirm', [
            'paid' => true,
        ])->assertOk();

        Sanctum::actingAs($this->couple);
        // 200 rather than 201 is itself part of the assertion: the existing
        // plan is handed back, no new subscription row is created.
        $this->select($this->package('Standard', 99.00))
            ->assertOk()
            ->assertJsonPath('data.package.id', $premium->id);

        $this->assertSame(1, Subscription::count(), 'Locking must not spawn a second subscription.');
    }

    public function test_a_paid_free_plan_does_not_lock_the_couple_out_of_upgrading(): void
    {
        Sanctum::actingAs($this->couple);
        $this->select($this->package('Free', 0.0))->assertSuccessful();

        $premium = $this->package('Premium', 199.00);

        // The free plan is "paid", but it must not behave as a lock. 201 here
        // (not 200) confirms a fresh row was opened for the upgrade.
        $this->select($premium)
            ->assertCreated()
            ->assertJsonPath('data.status', SubscriptionStatus::Pending->value)
            ->assertJsonPath('data.package.id', $premium->id);

        // The free row survives so the couple keeps free access while the
        // upgrade awaits payment.
        $this->assertSame(2, Subscription::count());
        $this->assertTrue(PlanCapabilities::forWedding($this->wedding->fresh())->seating);
    }

    // ------------------------------------------------------------------- pay

    public function test_payment_cannot_be_submitted_before_a_package_is_selected(): void
    {
        Sanctum::actingAs($this->couple);

        $this->pay()->assertStatus(422);
        $this->assertSame(0, Subscription::count());
    }

    public function test_submitting_payment_records_the_claim_and_moves_to_submitted(): void
    {
        Sanctum::actingAs($this->couple);
        $this->select($this->package('Premium', 199.00))->assertSuccessful();

        $this->pay()
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Submitted->value);

        $subscription = Subscription::latest('id')->first();
        $this->assertSame('khqr', $subscription->payment_method);
        $this->assertSame('TXN-000123', $subscription->payment_reference);
        $this->assertNotNull($subscription->submitted_at);
        $this->assertNull($subscription->paid_at);

        // Still nothing unlocked — an unconfirmed claim is not a payment.
        $this->assertFalse(PlanCapabilities::forWedding($this->wedding->fresh())->seating);
    }

    public function test_an_invalid_payment_method_is_rejected(): void
    {
        Sanctum::actingAs($this->couple);
        $this->select($this->package('Premium', 199.00))->assertSuccessful();

        $this->postJson("/api/weddings/{$this->wedding->id}/subscription/pay", [
            'payment_method' => 'paypal',
            'payment_reference' => 'TXN-000123',
        ])->assertStatus(422)->assertJsonValidationErrors('payment_method');
    }

    // --------------------------------------------------------- admin decision

    public function test_confirming_a_submitted_payment_activates_the_plan(): void
    {
        Sanctum::actingAs($this->couple);
        $this->select($this->package('Premium', 199.00))->assertSuccessful();
        $this->pay()->assertOk();
        $subscription = Subscription::latest('id')->first();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/subscriptions/{$subscription->id}/confirm", ['paid' => true])
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Paid->value);

        $this->assertNotNull($subscription->fresh()->paid_at);
        $this->assertTrue(PlanCapabilities::forWedding($this->wedding->fresh())->seating);
    }

    public function test_a_pending_subscription_cannot_be_confirmed(): void
    {
        Sanctum::actingAs($this->couple);
        $this->select($this->package('Premium', 199.00))->assertSuccessful();
        $subscription = Subscription::latest('id')->first();

        // Nobody has claimed to have paid this. A stale admin screen must not
        // be able to activate it.
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/subscriptions/{$subscription->id}/confirm", ['paid' => true])
            ->assertStatus(422);

        $this->assertSame(SubscriptionStatus::Pending, $subscription->fresh()->status);
        $this->assertFalse(PlanCapabilities::forWedding($this->wedding->fresh())->seating);
    }

    public function test_rejecting_a_submitted_payment_leaves_the_plan_locked(): void
    {
        Sanctum::actingAs($this->couple);
        $this->select($this->package('Premium', 199.00))->assertSuccessful();
        $this->pay()->assertOk();
        $subscription = Subscription::latest('id')->first();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/subscriptions/{$subscription->id}/confirm", ['paid' => false])
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Rejected->value);

        $this->assertNull($subscription->fresh()->paid_at);
        $this->assertFalse(PlanCapabilities::forWedding($this->wedding->fresh())->seating);
    }

    public function test_reverting_a_paid_payment_returns_it_to_the_review_queue(): void
    {
        Sanctum::actingAs($this->couple);
        $this->select($this->package('Premium', 199.00))->assertSuccessful();
        $this->pay()->assertOk();
        $subscription = Subscription::latest('id')->first();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/subscriptions/{$subscription->id}/confirm", ['paid' => true])->assertOk();

        $this->postJson("/api/admin/subscriptions/{$subscription->id}/revert")
            ->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::Submitted->value);

        // The claim survives the undo so it can be re-reviewed cleanly...
        $this->assertSame('TXN-000123', $subscription->fresh()->payment_reference);
        // ...but the access it granted is withdrawn immediately.
        $this->assertNull($subscription->fresh()->paid_at);
        $this->assertFalse(PlanCapabilities::forWedding($this->wedding->fresh())->seating);
    }

    public function test_a_rejected_payment_cannot_be_reverted_into_the_queue(): void
    {
        Sanctum::actingAs($this->couple);
        $this->select($this->package('Premium', 199.00))->assertSuccessful();
        $this->pay()->assertOk();
        $subscription = Subscription::latest('id')->first();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/subscriptions/{$subscription->id}/confirm", ['paid' => false])->assertOk();

        $this->postJson("/api/admin/subscriptions/{$subscription->id}/revert")
            ->assertStatus(422);

        $this->assertSame(SubscriptionStatus::Rejected, $subscription->fresh()->status);
    }

    // ------------------------------------------------------------------ authz

    public function test_a_couple_cannot_confirm_their_own_payment(): void
    {
        Sanctum::actingAs($this->couple);
        $this->select($this->package('Premium', 199.00))->assertSuccessful();
        $this->pay()->assertOk();
        $subscription = Subscription::latest('id')->first();

        // The whole model rests on this: self-confirmation would make the
        // payment step decorative.
        $this->postJson("/api/admin/subscriptions/{$subscription->id}/confirm", ['paid' => true])
            ->assertForbidden();

        $this->assertSame(SubscriptionStatus::Submitted, $subscription->fresh()->status);
        $this->assertFalse(PlanCapabilities::forWedding($this->wedding->fresh())->seating);
    }

    public function test_a_couple_cannot_manage_another_couples_subscription(): void
    {
        $stranger = User::factory()->create(['is_active' => true]);
        $stranger->roles()->attach(Role::firstWhere('key', RoleKey::Couple->value));
        $foreignWedding = Wedding::factory()->create(['created_by_user_id' => $stranger->id]);

        Sanctum::actingAs($this->couple);

        $this->postJson("/api/weddings/{$foreignWedding->id}/subscription", [
            'package_id' => $this->package('Premium', 199.00)->id,
        ])->assertNotFound();

        $this->assertSame(0, Subscription::count());
    }
}
