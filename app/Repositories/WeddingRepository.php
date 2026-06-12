<?php

namespace App\Repositories;

use App\Enums\RoleKey;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends EloquentRepository<Wedding>
 */
class WeddingRepository extends EloquentRepository
{
    protected string $modelClass = Wedding::class;

    /**
     * Weddings the user is allowed to see: super admins see all,
     * organizers see weddings they created or belong to, couples see
     * weddings they are members of.
     *
     * @return Builder<Wedding>
     */
    public function visibleTo(User $user): Builder
    {
        $query = $this->query();

        if ($user->hasRole(RoleKey::SuperAdmin)) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user) {
            $builder
                ->where('created_by_user_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->where('user_id', $user->id));
        });
    }

    public function searchVisibleTo(
        User $user,
        ?string $search = null,
        ?string $status = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->visibleTo($user)
            ->with(['package', 'createdBy'])
            ->withCount(['guests', 'invitations'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('wedding_name', 'ilike', "%{$search}%")
                        ->orWhere('wedding_code', 'ilike', "%{$search}%")
                        ->orWhere('bride_name', 'ilike', "%{$search}%")
                        ->orWhere('groom_name', 'ilike', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function canAccess(User $user, Wedding $wedding): bool
    {
        if ($user->hasRole(RoleKey::SuperAdmin)) {
            return true;
        }

        return $wedding->created_by_user_id === $user->id
            || $wedding->members()->where('user_id', $user->id)->exists();
    }
}
