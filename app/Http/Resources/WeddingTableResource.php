<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingTableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'table_name' => $this->table_name,
            'table_number' => $this->table_number,
            'capacity' => $this->capacity,
            'layout' => $this->layout,
            'seatings' => GuestSeatingResource::collection($this->whenLoaded('seatings')),
            'seatings_count' => $this->whenCounted('seatings'),
        ];
    }
}
