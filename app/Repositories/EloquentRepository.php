<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class EloquentRepository implements RepositoryInterface
{
    /**
     * @var class-string<TModel>
     */
    protected string $modelClass;

    public function query(): Builder
    {
        return $this->modelClass::query();
    }

    public function all(): Collection
    {
        return $this->query()->get();
    }

    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    public function create(array $attributes): Model
    {
        return $this->modelClass::create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model;
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage);
    }
}
