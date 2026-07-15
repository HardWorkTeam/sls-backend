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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GalleryService
{
    public function __construct(private readonly GalleryRepository $gallery) {}

    /**
     * Invalidate the public invitation cache for every invitation belonging
     * to this wedding so gallery changes (uploads, visibility toggles,
     * deletions) appear on the guest-facing page immediately.
     */
    private function revalidateWeddingCaches(Wedding $wedding): void
    {
        foreach ($wedding->invitations()->pluck('invitation_code') as $code) {
            Cache::forget(InvitationService::publicCacheKey((string) $code));
            $this->pingRsvpRevalidate((string) $code);
        }
    }

    private function pingRsvpRevalidate(string $code): void
    {
        $secret = config('services.rsvp.revalidate_secret');
        if (! $secret) {
            return;
        }

        $base = rtrim((string) config('services.rsvp.url'), '/');

        try {
            Http::timeout(2)
                ->withHeaders(['x-revalidate-secret' => $secret])
                ->post("{$base}/api/revalidate", ['code' => $code]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

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

    public function upload(Wedding $wedding, User $user, UploadedFile $file, ?int $albumId, ?bool $isPublic = null): MediaItem
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

        // If no album is specified, auto-assign to a default public "General Gallery" album
        if (! $albumId) {
            /** @var Album $defaultAlbum */
            $defaultAlbum = Album::firstOrCreate(
                ['wedding_id' => $wedding->id, 'name' => 'General Gallery'],
                ['description' => 'Default wedding gallery photos', 'is_public' => true]
            );
            $albumId = $defaultAlbum->id;
        }

        // Default is_public to album's visibility or true when not explicitly passed
        if ($isPublic === null) {
            $album = Album::find($albumId);
            $isPublic = $album ? (bool) $album->is_public : true;
        }

        $mediaItem = MediaItem::create([
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

        $this->revalidateWeddingCaches($wedding);

        return $mediaItem;
    }

    public function updateMedia(MediaItem $mediaItem, bool $isPublic): MediaItem
    {
        $mediaItem->update(['is_public' => $isPublic]);

        $this->revalidateWeddingCaches($mediaItem->wedding);

        return $mediaItem->fresh(['album']);
    }

    public function deleteMedia(MediaItem $mediaItem): void
    {
        $wedding = $mediaItem->wedding;

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

        $this->revalidateWeddingCaches($wedding);
    }
}
