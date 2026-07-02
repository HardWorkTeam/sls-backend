<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GuestResource extends JsonResource
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
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'note' => $this->note,
            'is_vip' => $this->is_vip,
            'check_in_token' => $this->check_in_token,
            'check_in_code' => $this->check_in_code,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'group' => GuestGroupResource::make($this->whenLoaded('group')),
            'invitation' => InvitationResource::make($this->whenLoaded('invitation')),
            'seating' => GuestSeatingResource::make($this->whenLoaded('seating')),
            'rsvp_responses_count' => $this->whenCounted('rsvpResponses'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
