<?php

namespace App\Services;

use App\Enums\RsvpStatus;
use App\Models\Invitation;
use App\Models\RsvpResponse;
use App\Models\Wedding;
use App\Repositories\RsvpRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class RsvpService
{
    public function __construct(private readonly RsvpRepository $rsvps) {}

    public function list(Wedding $wedding, ?string $status, ?string $search, int $perPage = 15): LengthAwarePaginator
    {
        return $this->rsvps->searchForWedding($wedding, $status, $search, $perPage);
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(Wedding $wedding): array
    {
        return $this->rsvps->statsForWedding($wedding);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(RsvpResponse $rsvp, array $attributes): RsvpResponse
    {
        if (isset($attributes['status']) && $attributes['status'] !== $rsvp->status) {
            $attributes['responded_at'] = now();
        }

        $this->rsvps->update($rsvp, $attributes);

        return $rsvp->load(['guest.group', 'invitation']);
    }

    public function delete(RsvpResponse $rsvp): void
    {
        $this->rsvps->delete($rsvp);
    }

    /**
     * Record an RSVP submitted from the public invitation page.
     * Matches an existing guest by phone or name when possible so the
     * couple's guest list stays in sync.
     *
     * @param  array{guest_name: string, phone?: string|null, number_of_guests: int, message?: string|null, status: string}  $attributes
     */
    public function submitPublic(Invitation $invitation, array $attributes): RsvpResponse
    {
        return DB::transaction(function () use ($invitation, $attributes) {
            // Serialize concurrent submissions from the same invitee (double
            // click, network retry): the find-then-create below would otherwise
            // record two RSVPs and double-count the headcount. The lock is
            // transaction-scoped (auto-released) and keyed on the invitation +
            // the identity we dedupe on (phone when given, else name).
            $identity = mb_strtolower(trim(($attributes['phone'] ?? null) ?: $attributes['guest_name']));
            DB::select('select pg_advisory_xact_lock(?, ?)', [
                $invitation->id,
                (int) hexdec(substr(md5($identity), 0, 7)),
            ]);

            return $this->recordPublic($invitation, $attributes);
        });
    }

    /**
     * @param  array{guest_name: string, phone?: string|null, number_of_guests: int, message?: string|null, status: string}  $attributes
     */
    private function recordPublic(Invitation $invitation, array $attributes): RsvpResponse
    {
        /** @var Wedding $wedding */
        $wedding = $invitation->wedding;

        $guest = $wedding->guests()
            ->when($attributes['phone'] ?? null, fn ($query, $phone) => $query->where('phone', $phone))
            ->when(empty($attributes['phone']), fn ($query) => $query->whereRaw('lower(name) = ?', [mb_strtolower($attributes['guest_name'])]))
            ->first();

        $existing = $invitation->rsvpResponses()
            ->when(
                $guest,
                fn ($query) => $query->where('guest_id', $guest->id),
                fn ($query) => $query
                    ->whereRaw('lower(guest_name) = ?', [mb_strtolower($attributes['guest_name'])])
                    ->when($attributes['phone'] ?? null, fn ($inner, $phone) => $inner->where('phone', $phone)),
            )
            ->first();

        $payload = [
            'wedding_id' => $wedding->id,
            'invitation_id' => $invitation->id,
            'guest_id' => $guest?->id,
            'guest_name' => $attributes['guest_name'],
            'phone' => $attributes['phone'] ?? null,
            'number_of_guests' => $attributes['number_of_guests'] ?? 1,
            'message' => $attributes['message'] ?? null,
            'status' => RsvpStatus::from($attributes['status'])->value,
            'responded_at' => now(),
        ];

        if ($existing) {
            $this->rsvps->update($existing, $payload);

            return $existing;
        }

        /** @var RsvpResponse $response */
        $response = $this->rsvps->create($payload);

        return $response;
    }
}
