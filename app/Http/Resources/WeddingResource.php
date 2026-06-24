<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_code' => $this->wedding_code,
            'wedding_name' => $this->wedding_name,
            'bride_name' => $this->bride_name,
            'groom_name' => $this->groom_name,
            'bride_photo_path' => $this->bride_photo_path,
            'groom_photo_path' => $this->groom_photo_path,
            'phone' => $this->phone,
            'email' => $this->email,
            'wedding_date' => $this->wedding_date?->toDateString(),
            'wedding_time' => $this->wedding_time,
            'ceremony_venue' => $this->ceremony_venue,
            'reception_venue' => $this->reception_venue,
            'google_map_link' => $this->google_map_link,
            'story_description' => $this->story_description,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'package' => PackageResource::make($this->whenLoaded('package')),
            'payment_status' => $this->whenLoaded(
                'subscriptions',
                fn () => $this->subscriptions->sortByDesc('id')->first()?->status?->value ?? 'unpaid',
            ),
            'created_by' => UserResource::make($this->whenLoaded('createdBy')),
            'members' => WeddingMemberResource::collection($this->whenLoaded('members')),
            'guests_count' => $this->whenCounted('guests'),
            'invitations_count' => $this->whenCounted('invitations'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
