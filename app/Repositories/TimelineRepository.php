<?php

namespace App\Repositories;

use App\Models\TimelineEvent;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<TimelineEvent>
 */
class TimelineRepository extends EloquentRepository
{
    protected string $modelClass = TimelineEvent::class;

    /**
     * @return Collection<int, TimelineEvent>
     */
    public function forWedding(Wedding $wedding, ?string $category = null): Collection
    {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderBy('sort_order')
            ->orderBy('starts_at')
            ->get();
    }
}
