<?php

namespace App\Repositories;

use App\Enums\InvitationStatus;
use App\Enums\WeddingStatus;
use App\Models\Invitation;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * @extends EloquentRepository<Invitation>
 */
class InvitationRepository extends EloquentRepository
{
    protected string $modelClass = Invitation::class;

    /**
     * @return Collection<int, Invitation>
     */
    public function forWedding(Wedding $wedding): Collection
    {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->with('template')
            ->withCount(['guests', 'rsvpResponses'])
            ->latest()
            ->get();
    }

    public function findPublishedByCode(string $code): ?Invitation
    {
        return $this->findByCode($code, publishedOnly: true);
    }

    public function findByCode(string $code, bool $publishedOnly = false): ?Invitation
    {
        $query = $this->query()
            ->where('invitation_code', $code)
            ->with([
                'template',
                'wedding.timelineEvents' => fn ($q) => $q->where('is_public', true)->orderBy('starts_at'),
                // Only public albums appear on the invitation, and within them
                // only photos the couple has kept public (the per-photo toggle).
                'wedding.albums' => fn ($q) => $q->where('is_public', true),
                'wedding.albums.mediaItems' => fn ($q) => $q->where('is_public', true),
            ]);

        if ($publishedOnly) {
            // A cancelled wedding takes its public pages down: guests must not
            // be able to view (or RSVP to) an invitation for an event that is
            // no longer happening, even though the invitation row itself still
            // says "published" (so reactivating the wedding restores it).
            $query
                ->where('status', InvitationStatus::Published->value)
                ->whereHas('wedding', fn ($wedding) => $wedding
                    ->where('status', '!=', WeddingStatus::Cancelled->value));
        }

        return $query->first();
    }

    public function generateUniqueCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while ($this->query()->withTrashed()->where('invitation_code', $code)->exists());

        return $code;
    }
}
