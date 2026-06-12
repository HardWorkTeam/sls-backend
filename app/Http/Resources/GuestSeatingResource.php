<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestSeatingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'guest_id' => $this->guest_id,
            'wedding_table_id' => $this->wedding_table_id,
            'seat_number' => $this->seat_number,
            'table' => WeddingTableResource::make($this->whenLoaded('table')),
            'guest' => GuestResource::make($this->whenLoaded('guest')),
        ];
    }
}
