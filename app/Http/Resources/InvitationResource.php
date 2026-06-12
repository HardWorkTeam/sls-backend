<?php

namespace App\Http\Resources;

use App\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'invitation_code' => $this->invitation_code,
            'title' => $this->title,
            'cover_image_path' => $this->cover_image_path,
            'settings' => $this->settings,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'public_url' => app(InvitationService::class)->publicUrl($this->resource),
            'template' => InvitationTemplateResource::make($this->whenLoaded('template')),
            'guests_count' => $this->whenCounted('guests'),
            'rsvp_responses_count' => $this->whenCounted('rsvpResponses'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
