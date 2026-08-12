<?php

namespace App\Services;

use App\Enums\MemberRole;
use App\Enums\RoleKey;
use App\Enums\WeddingStatus;
use App\Models\User;
use App\Models\Wedding;
use App\Models\WeddingMember;
use App\Repositories\GiftRepository;
use App\Repositories\RsvpRepository;
use App\Repositories\WeddingRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WeddingService
{
    /**
     * Allowed status transitions (mirrors the buttons the portals offer):
     * draft can go live or be called off; a live wedding can finish or be
     * called off; completed and cancelled weddings can be reactivated.
     */
    private const STATUS_TRANSITIONS = [
        WeddingStatus::Draft->value => [WeddingStatus::Published, WeddingStatus::Cancelled],
        WeddingStatus::Published->value => [WeddingStatus::Completed, WeddingStatus::Cancelled],
        WeddingStatus::Completed->value => [WeddingStatus::Published],
        WeddingStatus::Cancelled->value => [WeddingStatus::Published],
    ];

    public function __construct(
        private readonly WeddingRepository $weddings,
        private readonly RsvpRepository $rsvps,
        private readonly GiftRepository $gifts,
        private readonly UserService $users,
        private readonly InvitationService $invitationService,
    ) {}

    public function list(User $user, ?string $search, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->weddings->searchVisibleTo($user, $search, $status, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $user, array $attributes): Wedding
    {
        $attributes['wedding_code'] = $this->generateUniqueCode();
        $attributes['created_by_user_id'] = $user->id;
        $attributes['status'] = WeddingStatus::Draft->value;

        /** @var Wedding $wedding */
        $wedding = $this->weddings->create($attributes);

        $wedding->members()->create([
            'user_id' => $user->id,
            'member_role' => MemberRole::Member->value,
            'is_primary' => true,
        ]);

        return $wedding->load(['package', 'createdBy']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Wedding $wedding, array $attributes): Wedding
    {
        $this->weddings->update($wedding, $attributes);

        $this->invitationService->forgetWeddingPublicCaches($wedding);

        return $wedding->load(['package', 'createdBy']);
    }

    public function delete(Wedding $wedding): void
    {
        $this->weddings->delete($wedding);
    }

    public function changeStatus(Wedding $wedding, WeddingStatus $status): Wedding
    {
        $current = (string) $wedding->status;

        // Idempotent: re-applying the current status is a no-op, not an error
        // (double-clicked button, stale screen).
        if ($current === $status->value) {
            return $wedding;
        }

        abort_unless(
            in_array($status, self::STATUS_TRANSITIONS[$current] ?? [], true),
            422,
            "A {$current} wedding cannot be marked {$status->value}.",
        );

        $timestamps = [
            WeddingStatus::Published->value => 'published_at',
            WeddingStatus::Completed->value => 'completed_at',
            WeddingStatus::Cancelled->value => 'cancelled_at',
        ];

        $attributes = [
            'status' => $status->value,
            $timestamps[$status->value] => now(),
        ];

        // Reactivating clears the terminal timestamps — the wedding is live
        // again, so it is no longer "completed at" or "cancelled at" anything.
        if ($status === WeddingStatus::Published) {
            $attributes['completed_at'] = null;
            $attributes['cancelled_at'] = null;
        }

        $this->weddings->update($wedding, $attributes);

        // Cancelling hides the wedding's public invitation pages (and
        // reactivating restores them) — drop their cached payloads so the
        // change is immediate instead of waiting out the cache TTL.
        if ($status === WeddingStatus::Cancelled || $current === WeddingStatus::Cancelled->value) {
            $this->invitationService->forgetWeddingPublicCaches($wedding);
        }

        return $wedding;
    }

    public function addMember(Wedding $wedding, array $attributes): WeddingMember
    {
        if (WeddingMember::query()->where('user_id', $attributes['user_id'])->exists()) {
            throw ValidationException::withMessages([
                'user_id' => ['This user is already a member of a wedding.'],
            ]);
        }

        return $wedding->members()->create($attributes)->load('user');
    }

    /**
     * @param  array{member_role: string, is_primary?: bool}  $attributes
     */
    public function updateMember(WeddingMember $member, array $attributes): WeddingMember
    {
        $member->update($attributes);

        return $member->fresh('user');
    }

    /**
     * Invite a partner/member by email. Links an existing user, or creates a
     * new couple account when the email isn't registered yet.
     *
     * @param  array{name: string, email: string, member_role: string}  $attributes
     * @return array{member: WeddingMember, temp_password: string|null}
     */
    public function inviteMember(Wedding $wedding, array $attributes): array
    {
        $user = User::query()->where('email', $attributes['email'])->first();
        $tempPassword = null;

        if (! $user) {
            $tempPassword = Str::password(12, symbols: false);
            $user = $this->users->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => $tempPassword,
                'is_active' => true,
            ], [RoleKey::Couple->value]);
        }

        $member = $this->addMember($wedding, [
            'user_id' => $user->id,
            'member_role' => $attributes['member_role'],
        ]);

        return ['member' => $member, 'temp_password' => $tempPassword];
    }

    /**
     * The member is resolved through the wedding's own relation by the scoped
     * route binding (see routes/api.php), so it is already known to belong to
     * this wedding — same contract as every other service here, which take an
     * already-bound model.
     */
    public function removeMember(WeddingMember $member): void
    {
        if ($member->user_id === $member->wedding->created_by_user_id) {
            throw ValidationException::withMessages([
                'member' => ['The owner of the wedding cannot be removed.'],
            ]);
        }

        if ($member->user_id === auth()->id()) {
            throw ValidationException::withMessages([
                'member' => ['You cannot remove yourself from the wedding.'],
            ]);
        }

        $member->delete();
    }

    /**
     * Per-wedding dashboard statistics.
     *
     * @return array<string, mixed>
     */
    public function dashboard(Wedding $wedding): array
    {
        return [
            'rsvp' => $this->rsvps->statsForWedding($wedding),
            'rsvp_trend' => $this->rsvps->trendForWedding($wedding),
            'gifts' => $this->gifts->summaryForWedding($wedding),
            'guests_by_group' => $wedding->guestGroups()
                ->withCount('guests')
                ->get()
                ->map(fn ($group) => [
                    'group' => $group->name,
                    'type' => $group->type,
                    'total' => $group->guests_count,
                ]),
            'tables' => [
                'total' => $wedding->tables()->count(),
                'capacity' => (int) $wedding->tables()->sum('capacity'),
            ],
        ];
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = 'WED-'.strtoupper(Str::random(6));
        } while (Wedding::query()->withTrashed()->where('wedding_code', $code)->exists());

        return $code;
    }
}
