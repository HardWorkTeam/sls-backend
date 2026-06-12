<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface RepositoryInterface
{
    /**
     * @return Builder<TModel>
     */
    public function query(): Builder;

    /**
     * @return Collection<int, TModel>
     */
    public function all(): Collection;

    /**
     * @return TModel|null
     */
    public function find(int $id): ?Model;

    /**
     * @return TModel
     */
    public function findOrFail(int $id): Model;

    /**
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function create(array $attributes): Model;

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $attributes
     * @return TModel
     */
    public function update(Model $model, array $attributes): Model;

    /**
     * @param  TModel  $model
     */
    public function delete(Model $model): void;

    public function paginate(int $perPage = 15): LengthAwarePaginator;
}
