<?php

namespace App\Services;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\Wedding;
use App\Repositories\InvitationRepository;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Collection;

class InvitationService
{
    public function __construct(private readonly InvitationRepository $invitations) {}

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
        $this->invitations->update($invitation, $attributes);

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
}
