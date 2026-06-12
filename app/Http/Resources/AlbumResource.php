<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlbumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'name' => $this->name,
            'description' => $this->description,
            'is_public' => $this->is_public,
            'media_items_count' => $this->whenCounted('mediaItems'),
            'media_items' => MediaItemResource::collection($this->whenLoaded('mediaItems')),
        ];
    }
}
