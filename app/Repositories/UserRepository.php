<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends EloquentRepository<User>
 */
class UserRepository extends EloquentRepository
{
    protected string $modelClass = User::class;

    public function search(
        ?string $search = null,
        ?string $roleKey = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->query()
            ->with('roles')
            ->when($roleKey, fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('key', $roleKey)))
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);
    }
}
