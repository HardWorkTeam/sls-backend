<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload for the public RSVP website. Deliberately excludes anything
 * private (guest list, contact info beyond the couple's display data).
 */
class PublicInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $wedding = $this->wedding;

        return [
            'invitation_code' => $this->invitation_code,
            'title' => $this->title,
            'cover_image_path' => $this->cover_image_path,
            'settings' => $this->settings,
            'template' => InvitationTemplateResource::make($this->whenLoaded('template')),
            'wedding' => [
                'wedding_name' => $wedding->wedding_name,
                'bride_name' => $wedding->bride_name,
                'groom_name' => $wedding->groom_name,
                'bride_photo_path' => $wedding->bride_photo_path,
                'groom_photo_path' => $wedding->groom_photo_path,
                'wedding_date' => $wedding->wedding_date?->toDateString(),
                'wedding_time' => $wedding->wedding_time,
                'ceremony_venue' => $wedding->ceremony_venue,
                'reception_venue' => $wedding->reception_venue,
                'google_map_link' => $wedding->google_map_link,
                'story_description' => $wedding->story_description,
                'timeline_events' => TimelineEventResource::collection($wedding->timelineEvents),
                'albums' => AlbumResource::collection($wedding->albums->loadMissing([
                    'mediaItems' => fn ($query) => $query->where('is_public', true),
                ])),
            ],
        ];
    }
}
