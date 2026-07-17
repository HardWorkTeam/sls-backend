<?php

namespace App\Http\Resources;

use App\Models\MediaItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload for the public RSVP website. Deliberately excludes anything
 * private (guest list, contact info beyond the couple's display data).
 */
class PublicInvitationResource extends JsonResource
{
    /**
     * Check if an image URL corresponds to a MediaItem that was set to non-public in the gallery.
     *
     * @param  array<int, string>  $nonPublicPaths
     */
    private function isMediaNonPublic(?string $url, array $nonPublicPaths): bool
    {
        if (empty($url) || empty($nonPublicPaths)) {
            return false;
        }

        foreach ($nonPublicPaths as $path) {
            if (str_contains($url, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $wedding = $this->wedding;

        $nonPublicPaths = MediaItem::where('wedding_id', $wedding->id)
            ->where('is_public', false)
            ->pluck('storage_path')
            ->filter()
            ->all();

        $coverImagePath = $this->cover_image_path;
        if ($this->isMediaNonPublic($coverImagePath, $nonPublicPaths)) {
            $coverImagePath = null;
        }

        $bridePhotoPath = $wedding->bride_photo_path;
        if ($this->isMediaNonPublic($bridePhotoPath, $nonPublicPaths)) {
            $bridePhotoPath = null;
        }

        $groomPhotoPath = $wedding->groom_photo_path;
        if ($this->isMediaNonPublic($groomPhotoPath, $nonPublicPaths)) {
            $groomPhotoPath = null;
        }

        $settings = $this->settings ?? [];

        if (isset($settings['gallery_urls']) && is_array($settings['gallery_urls'])) {
            $settings['gallery_urls'] = array_values(array_filter(
                $settings['gallery_urls'],
                fn ($url) => ! $this->isMediaNonPublic((string) $url, $nonPublicPaths)
            ));
        }

        foreach (['groom', 'bride'] as $role) {
            if (isset($settings['couple_extended'][$role]['photo']) && $this->isMediaNonPublic((string) $settings['couple_extended'][$role]['photo'], $nonPublicPaths)) {
                $settings['couple_extended'][$role]['photo'] = null;
            }
        }

        return [
            'invitation_code' => $this->invitation_code,
            'title' => $this->title,
            'cover_image_path' => $coverImagePath,
            'settings' => $settings,
            'template' => InvitationTemplateResource::make($this->whenLoaded('template')),
            'wedding' => [
                'wedding_name' => $wedding->wedding_name,
                'bride_name' => $wedding->bride_name,
                'groom_name' => $wedding->groom_name,
                'bride_photo_path' => $bridePhotoPath,
                'groom_photo_path' => $groomPhotoPath,
                'wedding_date' => $wedding->wedding_date?->toDateString(),
                'wedding_time' => $wedding->wedding_time,
                'ceremony_venue' => $wedding->ceremony_venue,
                'reception_venue' => $wedding->reception_venue,
                'google_map_link' => $wedding->google_map_link,
                'story_description' => $wedding->story_description,
                'timeline_events' => TimelineEventResource::collection($wedding->timelineEvents),
                'albums' => AlbumResource::collection($wedding->albums->loadMissing('mediaItems')),
            ],
        ];
    }
}
