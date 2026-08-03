<?php

namespace Tests\Feature;

use App\Enums\RoleKey;
use App\Enums\SubscriptionStatus;
use App\Models\Expense;
use App\Models\Gift;
use App\Models\Guest;
use App\Models\GuestGroup;
use App\Models\Invitation;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\TimelineEvent;
use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingTable;
use App\Support\PlanCapabilities;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every `weddings/{wedding}/…/{child}` route resolves the child through the
 * wedding's own relation (`scopeBindings()` in routes/api.php) instead of each
 * controller action re-checking `wedding_id` by hand. These tests pin that
 * guarantee down per resource: a record belonging to someone else's wedding
 * must 404, must survive, and must be indistinguishable from an id that never
 * existed.
 */
class NestedResourceScopingTest extends TestCase
{
    use RefreshDatabase;

    private Wedding $ownedWedding;

    private Wedding $foreignWedding;

    protected function setUp(): void
    {
        parent::setUp();

        $coupleRole = Role::create(['key' => RoleKey::Couple->value, 'name' => 'Couple']);

        $owner = User::factory()->create(['is_active' => true]);
        $otherUser = User::factory()->create(['is_active' => true]);
        $owner->roles()->attach($coupleRole);
        $otherUser->roles()->attach($coupleRole);

        Sanctum::actingAs($owner);

        $this->ownedWedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);
        $this->foreignWedding = Wedding::factory()->create(['created_by_user_id' => $otherUser->id]);

        // Most nested modules sit behind `plan.module`, which 403s without a
        // PAID plan — that would mask the 404 these tests are about. Give both
        // weddings a paid plan that unlocks everything.
        $package = Package::create([
            'name' => 'Test Everything',
            'price' => 100,
            'currency' => 'USD',
            'is_active' => true,
            'capabilities' => (new PlanCapabilities(
                seating: true,
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

        $this->grantFullPlan($this->ownedWedding, $package);
        $this->grantFullPlan($this->foreignWedding, $package);
    }

    private function grantFullPlan(Wedding $wedding, Package $package): void
    {
        Subscription::create([
            'wedding_id' => $wedding->id,
            'package_id' => $package->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => SubscriptionStatus::Paid->value,
            'paid_at' => now(),
        ]);
    }

    /**
     * [route segment, model class, extra attributes needed by the table].
     *
     * @return iterable<string, array{string, class-string<Model>, array<string, mixed>}>
     */
    public static function nestedResourceProvider(): iterable
    {
        yield 'guests' => ['guests', Guest::class, ['name' => 'Foreign Guest']];
        yield 'guest groups' => ['guest-groups', GuestGroup::class, ['name' => 'Foreign Group']];
        yield 'gifts' => ['gifts', Gift::class, ['gift_type' => 'cash', 'amount' => 50, 'currency' => 'USD']];
        yield 'expenses' => ['expenses', Expense::class, ['item_name' => 'Foreign Expense', 'amount' => 50, 'currency' => 'USD']];
        yield 'timeline events' => ['timeline-events', TimelineEvent::class, ['title' => 'Foreign Event']];
        yield 'tables' => ['tables', WeddingTable::class, ['table_name' => 'Foreign Table', 'capacity' => 8]];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('nestedResourceProvider')]
    public function test_a_record_from_another_wedding_is_not_reachable(
        string $segment,
        string $modelClass,
        array $attributes,
    ): void {
        $foreignRecord = $modelClass::create($attributes + ['wedding_id' => $this->foreignWedding->id]);

        $base = "/api/weddings/{$this->ownedWedding->id}/{$segment}";
        $foreign = $this->deleteJson("{$base}/{$foreignRecord->getKey()}");
        $absent = $this->deleteJson("{$base}/999999");

        $foreign->assertNotFound();
        $absent->assertNotFound();

        // "Belongs to someone else" and "does not exist" must look identical to
        // the caller — no model class or probed id echoed back.
        $this->assertSame($absent->json('message'), $foreign->json('message'));
        $this->assertStringNotContainsString('App\\Models', (string) $foreign->json('message'));
        $this->assertStringNotContainsString((string) $foreignRecord->getKey(), (string) $foreign->json('message'));

        // The rejected call must not have touched the other wedding's data.
        $this->assertDatabaseHas($foreignRecord->getTable(), ['id' => $foreignRecord->getKey()]);
    }

    public function test_a_foreign_invitation_is_not_reachable(): void
    {
        $foreignInvitation = Invitation::factory()->create([
            'wedding_id' => $this->foreignWedding->id,
        ]);

        $this->getJson(
            "/api/weddings/{$this->ownedWedding->id}/invitations/{$foreignInvitation->id}",
        )->assertNotFound();
    }

    public function test_a_record_from_the_users_own_wedding_is_still_reachable(): void
    {
        $invitation = Invitation::factory()->create(['wedding_id' => $this->ownedWedding->id]);
        $guest = Guest::create(['wedding_id' => $this->ownedWedding->id, 'name' => 'Own Guest']);

        $this->getJson("/api/weddings/{$this->ownedWedding->id}/invitations/{$invitation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invitation->id);

        $this->putJson(
            "/api/weddings/{$this->ownedWedding->id}/guests/{$guest->id}",
            ['name' => 'Renamed Guest'],
        )->assertOk()->assertJsonPath('data.name', 'Renamed Guest');
    }

    public function test_a_nested_route_on_an_inaccessible_wedding_is_not_reachable(): void
    {
        $foreignInvitation = Invitation::factory()->create([
            'wedding_id' => $this->foreignWedding->id,
        ]);

        // wedding.access runs before binding resolution, so even a correctly
        // parented child stays invisible on a wedding the user cannot see.
        $this->getJson(
            "/api/weddings/{$this->foreignWedding->id}/invitations/{$foreignInvitation->id}",
        )->assertNotFound();
    }
}
