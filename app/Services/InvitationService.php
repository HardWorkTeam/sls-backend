<?php

namespace App\Services;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\MediaItem;
use App\Models\Wedding;
use App\Repositories\InvitationRepository;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class InvitationService
{
    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly GalleryService $galleryService,
    ) {}

    /**
     * @return Collection<int, Invitation>
     */
    public function listForWedding(Wedding $wedding): Collection
    {
        return $this->invitations->forWedding($wedding);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Wedding $wedding, array $attributes): Invitation
    {
        $attributes['wedding_id'] = $wedding->id;
        $attributes['invitation_code'] = $this->invitations->generateUniqueCode();
        $attributes['status'] = InvitationStatus::Draft->value;

        /** @var Invitation $invitation */
        $invitation = $this->invitations->create($attributes);

        return $invitation->load('template');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Invitation $invitation, array $attributes): Invitation
    {
        $oldUrls = $this->extractImageUrls($invitation->cover_image_path, $invitation->settings);

        $this->invitations->update($invitation, $attributes);

        $newUrls = $this->extractImageUrls($invitation->cover_image_path, $invitation->settings);
        $removedUrls = array_diff($oldUrls, $newUrls);

        foreach ($removedUrls as $url) {
            $storagePath = $this->urlToStoragePath((string) $url);
            if (! $storagePath) {
                continue;
            }
            $mediaItem = MediaItem::where('wedding_id', $invitation->wedding_id)
                ->where('storage_path', $storagePath)
                ->first();
            if ($mediaItem) {
                $this->galleryService->deleteMedia($mediaItem);
            }
        }

        return $invitation->load('template');
    }

    public function delete(Invitation $invitation): void
    {
        $this->invitations->delete($invitation);
    }

    public function publish(Invitation $invitation): Invitation
    {
        $this->invitations->update($invitation, [
            'status' => InvitationStatus::Published->value,
            'published_at' => now(),
        ]);

        return $invitation;
    }

    public function publicUrl(Invitation $invitation): string
    {
        $base = rtrim(config('services.rsvp.url', 'http://localhost:3002'), '/');

        return "{$base}/invite/{$invitation->invitation_code}";
    }

    /**
     * Render the invitation's public URL as an SVG QR code.
     */
    public function qrCodeSvg(Invitation $invitation, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($this->publicUrl($invitation));
    }

    public function findPublishedByCode(string $code): ?Invitation
    {
        return $this->invitations->findPublishedByCode($code);
    }

    public function findByCode(string $code): ?Invitation
    {
        return $this->invitations->findByCode($code);
    }

    /**
     * Collect every image URL stored in an invitation's fields.
     *
     * @return list<string>
     */
    private function extractImageUrls(?string $coverImagePath, ?array $settings): array
    {
        $urls = [];

        if ($coverImagePath) {
            $urls[] = $coverImagePath;
        }

        foreach (($settings['gallery_urls'] ?? []) as $url) {
            if ($url) {
                $urls[] = (string) $url;
            }
        }

        foreach (['groom', 'bride'] as $role) {
            $photo = $settings['couple_extended'][$role]['photo'] ?? null;
            if ($photo) {
                $urls[] = (string) $photo;
            }
        }

        if ($url = $settings['bank_account']['qr_url'] ?? null) {
            $urls[] = (string) $url;
        }

        return array_values(array_unique($urls));
    }

    /**
     * Reverse a public image URL back to the storage_path stored in MediaItem.
     * Returns null for external URLs that were not uploaded via the gallery.
     */
    private function urlToStoragePath(string $url): ?string
    {
        $disk = config('filesystems.media_disk', 'public');

        if ($disk === 'cloudinary') {
            $cloudName = config('services.cloudinary.cloud_name');
            $prefix = "https://res.cloudinary.com/{$cloudName}/";
            if (! str_starts_with($url, $prefix)) {
                return null;
            }
            // Strip cloud prefix + resource type segment: "image/upload/" or "video/upload/"
            $rest = substr($url, strlen($prefix));
            $parts = explode('/upload/', $rest, 2);

            return $parts[1] ?? null;
        }

        try {
            $base = Storage::disk($disk)->url('');
            if (str_starts_with($url, $base)) {
                return ltrim(substr($url, strlen($base)), '/');
            }
        } catch (\Throwable) {
            // ignore — URL was not from this disk
        }

        return null;
    }
}
