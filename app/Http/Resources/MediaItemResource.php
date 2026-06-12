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
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'album_id' => $this->album_id,
            'media_type' => $this->media_type,
            'url' => Storage::disk('public')->url($this->storage_path),
            'thumbnail_url' => $this->thumbnail_path
                ? Storage::disk('public')->url($this->thumbnail_path)
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
