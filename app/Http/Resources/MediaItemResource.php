<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class MediaItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    private function resolveUrl(string $path, string $resourceType = 'image'): string
    {
        $disk = config('filesystems.media_disk', 'public');

        if ($disk === 'cloudinary') {
            $cloudName = config('services.cloudinary.cloud_name');

            return "https://res.cloudinary.com/{$cloudName}/{$resourceType}/upload/{$path}";
        }

        return Storage::disk($disk)->url($path);
    }

    public function toArray(Request $request): array
    {
        $resourceType = $this->media_type === 'video' ? 'video' : 'image';

        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'album_id' => $this->album_id,
            'media_type' => $this->media_type,
            'url' => $this->resolveUrl($this->storage_path, $resourceType),
            'thumbnail_url' => $this->thumbnail_path
                ? $this->resolveUrl($this->thumbnail_path)
                : null,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'is_public' => $this->is_public,
            'album' => AlbumResource::make($this->whenLoaded('album')),
            'uploaded_by' => UserResource::make($this->whenLoaded('uploadedBy')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
