<?php

namespace App\Repositories;

use App\Enums\RsvpStatus;
use App\Models\RsvpResponse;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends EloquentRepository<RsvpResponse>
 */
class RsvpRepository extends EloquentRepository
{
    protected string $modelClass = RsvpResponse::class;

    public function searchForWedding(
        Wedding $wedding,
        ?string $status = null,
        ?string $search = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->with(['guest.group', 'invitation'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search, function (Builder $query) use ($search) {
                $query->where(function (Builder $builder) use ($search) {
                    $builder
                        ->where('guest_name', 'ilike', "%{$search}%")
                        ->orWhere('phone', 'ilike', "%{$search}%");
                });
            })
            ->latest('responded_at')
            ->paginate($perPage);
    }

    /**
     * @return array{total_guests: int, confirmed: int, declined: int, maybe: int, pending: int, expected_attendees: int}
     */
    public function statsForWedding(Wedding $wedding): array
    {
        $byStatus = $this->query()
            ->where('wedding_id', $wedding->id)
            ->selectRaw('status, count(*) as responses, sum(number_of_guests) as attendees')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $totalGuests = $wedding->guests()->count();
        $responded = (int) $byStatus->sum('responses');

        return [
            'total_guests' => $totalGuests,
            'confirmed' => (int) ($byStatus[RsvpStatus::Accepted->value]->responses ?? 0),
            'declined' => (int) ($byStatus[RsvpStatus::Declined->value]->responses ?? 0),
            'maybe' => (int) ($byStatus[RsvpStatus::Maybe->value]->responses ?? 0),
            'pending' => max($totalGuests - $responded, 0),
            'expected_attendees' => (int) ($byStatus[RsvpStatus::Accepted->value]->attendees ?? 0),
        ];
    }

    /**
     * Daily counts of responses for trend charts.
     *
     * @return list<array{date: string, accepted: int, declined: int, maybe: int}>
     */
    public function trendForWedding(Wedding $wedding, int $days = 30): array
    {
        $rows = $this->query()
            ->where('wedding_id', $wedding->id)
            ->whereNotNull('responded_at')
            ->where('responded_at', '>=', now()->subDays($days)->startOfDay())
            ->selectRaw('date(responded_at) as date, status, count(*) as total')
            ->groupBy('date', 'status')
            ->orderBy('date')
            ->get();

        return $rows
            ->groupBy('date')
            ->map(fn ($group, $date) => [
                'date' => (string) $date,
                'accepted' => (int) $group->firstWhere('status', RsvpStatus::Accepted->value)?->total,
                'declined' => (int) $group->firstWhere('status', RsvpStatus::Declined->value)?->total,
                'maybe' => (int) $group->firstWhere('status', RsvpStatus::Maybe->value)?->total,
            ])
            ->values()
            ->all();
    }
}
