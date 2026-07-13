<?php

namespace App\Services;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\MediaItem;
use App\Models\Wedding;
use App\Repositories\InvitationRepository;
use App\Support\PlanCapabilities;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class InvitationService
{
    public function __construct(
        private readonly InvitationRepository $invitations,
        private readonly GalleryService $galleryService,
    ) {}

    /** Cache key for a code's serialized public payload (see PublicInvitationController). */
    public static function publicCacheKey(string $code): string
    {
        return "public_invitation:{$code}";
    }

    /** Drop the cached public payload so the next guest sees fresh content. */
    private function forgetPublicCache(Invitation $invitation): void
    {
        Cache::forget(self::publicCacheKey($invitation->invitation_code));
        $this->pingRsvpRevalidate($invitation->invitation_code);
    }

    /**
     * Drop every cached public payload for a wedding's invitations. Used when
     * the WEDDING's visibility changes as a whole (cancelled / reactivated) so
     * guests don't keep seeing a cached page for up to the TTL.
     */
    public function forgetWeddingPublicCaches(Wedding $wedding): void
    {
        foreach ($wedding->invitations()->pluck('invitation_code') as $code) {
            Cache::forget(self::publicCacheKey((string) $code));
            $this->pingRsvpRevalidate((string) $code);
        }
    }

    /**
     * Best-effort: tell the RSVP site to drop its Next.js Data Cache entry for
     * this code so edits appear immediately rather than after the TTL. Disabled
     * when no secret is configured; failures are swallowed (the TTL is the
     * safety net) so a couple's save never fails because the site is down.
     */
    private function pingRsvpRevalidate(string $code): void
    {
        $secret = config('services.rsvp.revalidate_secret');
        if (! $secret) {
            return;
        }

        $base = rtrim((string) config('services.rsvp.url'), '/');

        try {
            Http::timeout(2)
                ->withHeaders(['x-revalidate-secret' => $secret])
                ->post("{$base}/api/revalidate", ['code' => $code]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

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
        $this->assertDesignCapacity($wedding, $attributes['invitation_template_id'] ?? null);

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
        if (array_key_exists('invitation_template_id', $attributes)) {
            $this->assertDesignCapacity(
                $invitation->wedding,
                $attributes['invitation_template_id'],
                ignoreInvitationId: $invitation->id,
            );
        }

        $oldUrls = $this->extractImageUrls($invitation->cover_image_path, $invitation->settings);

        $this->invitations->update($invitation, $attributes);

        $this->forgetPublicCache($invitation);

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
        $this->forgetPublicCache($invitation);
        $this->invitations->delete($invitation);
    }

    public function publish(Invitation $invitation): Invitation
    {
        $this->invitations->update($invitation, [
            'status' => InvitationStatus::Published->value,
            'published_at' => now(),
        ]);

        $this->forgetPublicCache($invitation);

        return $invitation;
    }

    public function unpublish(Invitation $invitation): Invitation
    {
        $this->invitations->update($invitation, [
            'status' => InvitationStatus::Draft->value,
            'published_at' => null,
        ]);

        $this->forgetPublicCache($invitation);

        return $invitation;
    }

    public function publicUrl(Invitation $invitation): string
    {
        $base = rtrim(config('services.rsvp.url', 'http://localhost:3002'), '/');

        $url = "{$base}/invite/{$invitation->invitation_code}";

        if ($invitation->status !== InvitationStatus::Published->value) {
            $token = hash_hmac('sha256', $invitation->invitation_code, (string) config('app.key'));
            $url .= "?preview_token={$token}";
        }

        return $url;
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
     * Enforce the plan's invitation-design cap: a wedding may use at most N
     * distinct templates across its invitations (null = unlimited). Reusing a
     * template the wedding already uses never consumes a new slot.
     */
    private function assertDesignCapacity(Wedding $wedding, ?int $templateId, ?int $ignoreInvitationId = null): void
    {
        $limit = PlanCapabilities::forWedding($wedding)->invitationDesignLimit;

        // A plan with a 0 allowance (e.g. Free) includes no invitation designs
        // at all — block creation regardless of template.
        abort_if(
            $limit === 0,
            422,
            'Your plan does not include invitation designs. Upgrade your plan to create one.',
        );

        if ($templateId === null || $limit === null) {
            return;
        }

        $usedTemplateIds = $wedding->invitations()
            ->whereNotNull('invitation_template_id')
            ->when($ignoreInvitationId, fn ($query) => $query->where('id', '!=', $ignoreInvitationId))
            ->distinct()
            ->pluck('invitation_template_id');

        if (! $usedTemplateIds->contains($templateId) && $usedTemplateIds->count() >= $limit) {
            abort(422, "Your plan allows up to {$limit} invitation design(s). Upgrade your plan to use more.");
        }
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
