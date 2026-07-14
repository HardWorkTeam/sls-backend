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
use Illuminate\Support\Facades\Http;
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

    private function mediaDisk(): string
    {
        return config('filesystems.media_disk', 'public');
    }

    private function isCloudinary(): bool
    {
        return $this->mediaDisk() === 'cloudinary';
    }

    private function cloudinarySign(array $params): string
    {
        ksort($params);
        $str = implode('&', array_map(
            fn ($k, $v) => "{$k}={$v}",
            array_keys($params),
            array_values($params),
        )).config('services.cloudinary.api_secret');

        return hash('sha256', $str);
    }

    private function cloudinaryUpload(UploadedFile $file, string $folder, string $resourceType): string
    {
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey = config('services.cloudinary.api_key');
        $timestamp = time();
        $signParams = ['folder' => $folder, 'timestamp' => $timestamp];

        $response = Http::attach('file', fopen($file->getRealPath(), 'rb'), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/upload", [
                'api_key' => $apiKey,
                'timestamp' => $timestamp,
                'folder' => $folder,
                'signature' => $this->cloudinarySign($signParams),
            ]);

        $response->throw();

        return $response->json('public_id');
    }

    private function cloudinaryDestroy(string $publicId, string $resourceType = 'image'): void
    {
        $cloudName = config('services.cloudinary.cloud_name');
        $apiKey = config('services.cloudinary.api_key');
        $timestamp = time();
        $signParams = ['public_id' => $publicId, 'timestamp' => $timestamp];

        Http::post("https://api.cloudinary.com/v1_1/{$cloudName}/{$resourceType}/destroy", [
            'api_key' => $apiKey,
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'signature' => $this->cloudinarySign($signParams),
        ])->throw();
    }

    public function upload(Wedding $wedding, User $user, UploadedFile $file, ?int $albumId, bool $isPublic): MediaItem
    {
        $mime = (string) $file->getMimeType();
        $clientMime = (string) $file->getClientMimeType();
        $ext = strtolower($file->getClientOriginalExtension());

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'heic', 'heif', 'avif', 'tiff'];
        $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'mkv', 'webm', '3gp', 'm4v', 'quicktime', 'ogv'];

        $isImage = str_starts_with($mime, 'image/')
            || str_starts_with($clientMime, 'image/')
            || in_array($ext, $imageExtensions, true);

        $isVideo = str_starts_with($mime, 'video/')
            || str_starts_with($clientMime, 'video/')
            || in_array($ext, $videoExtensions, true);

        $mediaType = $isVideo ? MediaType::Video->value : ($isImage ? MediaType::Photo->value : MediaType::Document->value);

        $resolvedMime = ($mime !== 'application/octet-stream' && $mime !== '')
            ? $mime
            : (($clientMime !== '' && $clientMime !== 'application/octet-stream') ? $clientMime : match ($ext) {
                'heic' => 'image/heic',
                'heif' => 'image/heif',
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'mov' => 'video/quicktime',
                'mp4' => 'video/mp4',
                default => $mime ?: 'application/octet-stream',
            });

        if ($this->isCloudinary()) {
            $resourceType = $isVideo ? 'video' : ($isImage ? 'image' : 'raw');
            $path = $this->cloudinaryUpload($file, "weddings/{$wedding->id}/gallery", $resourceType);
        } else {
            $path = Storage::disk($this->mediaDisk())->putFile(
                "weddings/{$wedding->id}/gallery",
                $file,
                'public',
            );
        }

        // Inherit is_public from the album when the caller hasn't explicitly set it.
        if (! $isPublic && $albumId) {
            $isPublic = (bool) Album::find($albumId)?->is_public;
        }

        return MediaItem::create([
            'wedding_id' => $wedding->id,
            'album_id' => $albumId,
            'uploaded_by_user_id' => $user->id,
            'media_type' => $mediaType,
            'storage_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $resolvedMime,
            'size_bytes' => $file->getSize(),
            'is_public' => $isPublic,
        ])->load('album');
    }

    public function updateMedia(MediaItem $mediaItem, bool $isPublic): MediaItem
    {
        $mediaItem->update(['is_public' => $isPublic]);

        return $mediaItem->fresh(['album']);
    }

    public function deleteMedia(MediaItem $mediaItem): void
    {
        if ($this->isCloudinary()) {
            $resourceType = $mediaItem->media_type === 'video' ? 'video' : ($mediaItem->media_type === 'photo' ? 'image' : 'raw');
            $this->cloudinaryDestroy($mediaItem->storage_path, $resourceType);
            if ($mediaItem->thumbnail_path) {
                $this->cloudinaryDestroy($mediaItem->thumbnail_path);
            }
        } else {
            $paths = array_filter([$mediaItem->storage_path, $mediaItem->thumbnail_path]);
            if ($paths) {
                Storage::disk($this->mediaDisk())->delete($paths);
            }
        }

        $mediaItem->forceDelete();
    }
}
