<?php

namespace App\Repositories;

use App\Models\Announcement;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends EloquentRepository<Announcement>
 */
class AnnouncementRepository extends EloquentRepository
{
    protected string $modelClass = Announcement::class;

    public function forWedding(Wedding $wedding, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->with('createdBy')
            ->withCount('notificationLogs')
            ->latest()
            ->paginate($perPage);
    }
}
