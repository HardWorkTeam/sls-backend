<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'guest_id' => $this->guest_id,
            'gift_type' => $this->gift_type,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'item_name' => $this->item_name,
            'note' => $this->note,
            'received_at' => $this->received_at?->toIso8601String(),
            'guest' => GuestResource::make($this->whenLoaded('guest')),
        ];
    }
}
