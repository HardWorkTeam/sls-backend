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
    /**
     * Invitation codes whose RSVP-site cache entry still needs invalidating.
     * Held as a set so a wedding-wide flush pings each code once.
     *
     * @var array<string, true>
     */
    private array $pendingRevalidations = [];

    private bool $revalidationScheduled = false;

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
        $this->queueRsvpRevalidate($invitation->invitation_code);
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
            $this->queueRsvpRevalidate((string) $code);
        }
    }

    /**
     * Best-effort: tell the RSVP site to drop its Next.js Data Cache entry for
     * this code so edits appear immediately rather than after the TTL.
     *
     * The ping is an outbound HTTP call to another host, so it runs once the
     * response has been sent rather than inside the couple's save — a wedding
     * with several invitations otherwise paid for one round trip per code, in
     * series, before it saw its own save confirmed. Our own cache above is
     * still cleared synchronously, so the API can never serve a stale payload;
     * this only shortens how long the RSVP site's copy lags. Disabled when no
     * secret is configured; failures are swallowed (the TTL is the safety net)
     * so a couple's save never fails because the site is down.
     */
    private function queueRsvpRevalidate(string $code): void
    {
        if (! config('services.rsvp.revalidate_secret')) {
            return;
        }

        $this->pendingRevalidations[$code] = true;

        if ($this->revalidationScheduled) {
            return;
        }

        $this->revalidationScheduled = true;

        app()->terminating(function (): void {
            $codes = array_keys($this->pendingRevalidations);
            $this->pendingRevalidations = [];
            $this->revalidationScheduled = false;

            foreach ($codes as $code) {
                $this->pingRsvpRevalidate($code);
            }
        });
    }

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

        $settings = is_array($attributes['settings'] ?? null) ? $attributes['settings'] : [];
        if (! array_key_exists('wedding_days', $settings)) {
            $settings = $this->inheritWeddingDays($wedding, $settings);
        }

        $attributes['wedding_id'] = $wedding->id;
        $attributes['invitation_code'] = $this->invitations->generateUniqueCode();
        $attributes['status'] = InvitationStatus::Draft->value;
        $attributes['settings'] = $settings ?: null;

        /** @var Invitation $invitation */
        $invitation = $this->invitations->create($attributes);

        return $invitation->load('template');
    }

    /**
     * Wedding days describe the wedding rather than a particular design. Carry
     * them into additional invitations so a newly selected design is not blank.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function inheritWeddingDays(Wedding $wedding, array $settings): array
    {
        $sourceSettings = $wedding->invitations()
            ->latest()
            ->get(['settings'])
            ->map(fn (Invitation $invitation) => $invitation->settings ?? [])
            ->first(fn (array $candidate) => ! empty($candidate['wedding_days']));

        if ($sourceSettings) {
            $settings['wedding_days'] = $sourceSettings['wedding_days'];
            $settings['main_wedding_day_index'] = $sourceSettings['main_wedding_day_index'] ?? 0;

            return $settings;
        }

        if ($wedding->wedding_date) {
            $settings['wedding_days'] = [[
                'date' => $wedding->wedding_date->format('Y-m-d'),
                'time' => $wedding->wedding_time ? substr($wedding->wedding_time, 0, 5) : '',
                'venue' => $wedding->ceremony_venue ?? '',
            ]];
            $settings['main_wedding_day_index'] = 0;
        }

        return $settings;
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

    /**
     * The clean public URL — guests use this to view the invitation.
     * Only works for published invitations; unpublished ones will 404.
     */
    public function publicUrl(Invitation $invitation): string
    {
        $base = rtrim(config('services.rsvp.url', 'http://localhost:3002'), '/');

        return "{$base}/invite/{$invitation->invitation_code}";
    }

    /**
     * Preview URL with an HMAC token — always works regardless of publish status.
     * Used by the editor iframe so the couple can see their draft in real time.
     */
    public function previewUrl(Invitation $invitation): string
    {
        $token = hash_hmac('sha256', $invitation->invitation_code, (string) config('app.key'));

        return $this->publicUrl($invitation)."?preview_token={$token}";
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
