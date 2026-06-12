<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'member_role' => $this->member_role,
            'is_primary' => $this->is_primary,
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
