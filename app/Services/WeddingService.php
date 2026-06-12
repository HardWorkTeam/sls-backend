<?php

namespace App\Services;

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
    public function __construct(
        private readonly WeddingRepository $weddings,
        private readonly RsvpRepository $rsvps,
        private readonly GiftRepository $gifts,
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

        return $wedding->load(['package', 'createdBy']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Wedding $wedding, array $attributes): Wedding
    {
        $this->weddings->update($wedding, $attributes);

        return $wedding->load(['package', 'createdBy']);
    }

    public function delete(Wedding $wedding): void
    {
        $this->weddings->delete($wedding);
    }

    public function changeStatus(Wedding $wedding, WeddingStatus $status): Wedding
    {
        $timestamps = [
            WeddingStatus::Published->value => 'published_at',
            WeddingStatus::Completed->value => 'completed_at',
            WeddingStatus::Cancelled->value => 'cancelled_at',
        ];

        $attributes = ['status' => $status->value];

        if (isset($timestamps[$status->value])) {
            $attributes[$timestamps[$status->value]] = now();
        }

        $this->weddings->update($wedding, $attributes);

        return $wedding;
    }

    /**
     * @param  array{user_id: int, member_role: string, is_primary?: bool}  $attributes
     */
    public function addMember(Wedding $wedding, array $attributes): WeddingMember
    {
        if ($wedding->members()->where('user_id', $attributes['user_id'])->exists()) {
            throw ValidationException::withMessages([
                'user_id' => ['This user is already a member of the wedding.'],
            ]);
        }

        return $wedding->members()->create($attributes)->load('user');
    }

    public function removeMember(Wedding $wedding, WeddingMember $member): void
    {
        abort_unless($member->wedding_id === $wedding->id, 404);

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
