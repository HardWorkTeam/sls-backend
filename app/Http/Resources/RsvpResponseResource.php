<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RsvpResponseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'invitation_id' => $this->invitation_id,
            'guest_id' => $this->guest_id,
            'guest_name' => $this->guest_name,
            'phone' => $this->phone,
            'number_of_guests' => $this->number_of_guests,
            'message' => $this->message,
            'status' => $this->status,
            'responded_at' => $this->responded_at?->toIso8601String(),
            'guest' => GuestResource::make($this->whenLoaded('guest')),
            'invitation' => InvitationResource::make($this->whenLoaded('invitation')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
