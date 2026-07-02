<?php

namespace App\Repositories;

use App\Models\Guest;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<Guest>
 */
class GuestRepository extends EloquentRepository
{
    protected string $modelClass = Guest::class;

    /**
     * @param  array{search?: string|null, guest_group_id?: int|null, is_vip?: bool|null}  $filters
     */
    public function searchForWedding(
        Wedding $wedding,
        array $filters = [],
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->forWeddingQuery($wedding, $filters)
            ->with(['group', 'invitation', 'seating.table'])
            ->withCount('rsvpResponses')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @param  array{search?: string|null, guest_group_id?: int|null, is_vip?: bool|null}  $filters
     * @return Collection<int, Guest>
     */
    public function allForWedding(Wedding $wedding, array $filters = []): Collection
    {
        return $this->forWeddingQuery($wedding, $filters)
            ->with(['group', 'seating.table'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve a guest by their check-in credential within a wedding. Accepts
     * either the opaque QR token or the short, human-friendly code (which staff
     * may type in any case).
     */
    public function findByToken(Wedding $wedding, string $token): ?Guest
    {
        $code = strtoupper(trim($token));

        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->where(function ($query) use ($token, $code) {
                $query->where('check_in_token', $token)
                    ->orWhere('check_in_code', $code);
            })
            ->first();
    }

    /**
     * @return Collection<int, Guest>
     */
    public function unseatedForWedding(Wedding $wedding): Collection
    {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->whereDoesntHave('seating')
            ->orderBy('guest_group_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array{search?: string|null, guest_group_id?: int|null, is_vip?: bool|null}  $filters
     * @return Builder<Guest>
     */
    private function forWeddingQuery(Wedding $wedding, array $filters): Builder
    {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->when($filters['guest_group_id'] ?? null, fn (Builder $query, $groupId) => $query->where('guest_group_id', $groupId))
            ->when(isset($filters['is_vip']), fn (Builder $query) => $query->where('is_vip', (bool) $filters['is_vip']))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            });
    }
}
