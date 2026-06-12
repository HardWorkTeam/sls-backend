<?php

namespace App\Services;

use App\Enums\MediaType;
use App\Models\Album;
use App\Models\MediaItem;
use App\Models\User;
use App\Models\Wedding;
use App\Repositories\GalleryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GalleryService
{
    public function __construct(private readonly GalleryRepository $gallery) {}

    /**
     * @return Collection<int, Album>
     */
    public function albums(Wedding $wedding): Collection
    {
        return $this->gallery->albumsForWedding($wedding);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createAlbum(Wedding $wedding, array $attributes): Album
    {
        $attributes['wedding_id'] = $wedding->id;

        /** @var Album $album */
        $album = $this->gallery->create($attributes);

        return $album->loadCount('mediaItems');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateAlbum(Album $album, array $attributes): Album
    {
        $this->gallery->update($album, $attributes);

        return $album->loadCount('mediaItems');
    }

    public function deleteAlbum(Album $album): void
    {
        $this->gallery->delete($album);
    }

    public function media(Wedding $wedding, ?int $albumId, ?string $mediaType, int $perPage = 24): LengthAwarePaginator
    {
        return $this->gallery->mediaForWedding($wedding, $albumId, $mediaType, $perPage);
    }

    public function upload(Wedding $wedding, User $user, UploadedFile $file, ?int $albumId, bool $isPublic): MediaItem
    {
        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
        $path = $file->store("weddings/{$wedding->id}/gallery", 'public');

        return MediaItem::create([
            'wedding_id' => $wedding->id,
            'album_id' => $albumId,
            'uploaded_by_user_id' => $user->id,
            'media_type' => $isVideo ? MediaType::Video->value : MediaType::Photo->value,
            'storage_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'is_public' => $isPublic,
        ])->load('album');
    }

    public function deleteMedia(MediaItem $mediaItem): void
    {
        Storage::disk('public')->delete(array_filter([
            $mediaItem->storage_path,
            $mediaItem->thumbnail_path,
        ]));

        $mediaItem->forceDelete();
    }
}
