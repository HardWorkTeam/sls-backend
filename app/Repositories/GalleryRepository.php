<?php

namespace App\Repositories;

use App\Models\Album;
use App\Models\MediaItem;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<Album>
 */
class GalleryRepository extends EloquentRepository
{
    protected string $modelClass = Album::class;

    /**
     * @return Collection<int, Album>
     */
    public function albumsForWedding(Wedding $wedding): Collection
    {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->withCount('mediaItems')
            ->orderBy('name')
            ->get();
    }

    public function mediaForWedding(
        Wedding $wedding,
        ?int $albumId = null,
        ?string $mediaType = null,
        int $perPage = 24,
    ): LengthAwarePaginator {
        return MediaItem::query()
            ->where('wedding_id', $wedding->id)
            ->with(['album', 'uploadedBy'])
            ->when($albumId, fn (Builder $query) => $query->where('album_id', $albumId))
            ->when($mediaType, fn (Builder $query) => $query->where('media_type', $mediaType))
            ->latest()
            ->paginate($perPage);
    }
}
