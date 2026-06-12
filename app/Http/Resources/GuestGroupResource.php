<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestGroupResource extends JsonResource
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
            'type' => $this->type,
            'sort_order' => $this->sort_order,
            'guests_count' => $this->whenCounted('guests'),
        ];
    }
}
