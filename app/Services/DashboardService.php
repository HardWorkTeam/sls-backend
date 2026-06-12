<?php

namespace App\Services;

use App\Enums\RsvpStatus;
use App\Enums\WeddingStatus;
use App\Models\Guest;
use App\Models\RsvpResponse;
use App\Models\User;
use App\Models\Wedding;
use App\Repositories\WeddingRepository;

class DashboardService
{
    public function __construct(private readonly WeddingRepository $weddings) {}

    /**
     * Global admin dashboard: cards + chart datasets, scoped to the
     * weddings the requesting user may see.
     *
     * @return array<string, mixed>
     */
    public function overview(User $user): array
    {
        $weddingIds = $this->weddings->visibleTo($user)->pluck('id');

        $totalGuests = Guest::query()->whereIn('wedding_id', $weddingIds)->count();

        $rsvpByStatus = RsvpResponse::query()
            ->whereIn('wedding_id', $weddingIds)
            ->selectRaw('status, count(*) as total, sum(number_of_guests) as attendees')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalRsvp = (int) $rsvpByStatus->sum();
        $confirmed = (int) ($rsvpByStatus[RsvpStatus::Accepted->value] ?? 0);

        $weddingsByStatus = Wedding::query()
            ->whereIn('id', $weddingIds)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $trend = RsvpResponse::query()
            ->whereIn('wedding_id', $weddingIds)
            ->whereNotNull('responded_at')
            ->where('responded_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('date(responded_at) as date, count(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => (string) $row->date, 'total' => (int) $row->total]);

        $guestDistribution = Guest::query()
            ->whereIn('guests.wedding_id', $weddingIds)
            ->leftJoin('guest_groups', 'guests.guest_group_id', '=', 'guest_groups.id')
            ->selectRaw("coalesce(guest_groups.type, 'custom') as type, count(*) as total")
            ->whereNull('guests.deleted_at')
            ->groupBy('guest_groups.type')
            ->pluck('total', 'type');

        return [
            'cards' => [
                'total_weddings' => $weddingIds->count(),
                'total_guests' => $totalGuests,
                'total_rsvp' => $totalRsvp,
                'attendance_rate' => $totalRsvp > 0
                    ? round($confirmed / $totalRsvp * 100, 1)
                    : 0.0,
            ],
            'charts' => [
                'rsvp_trend' => $trend,
                'guest_distribution' => $guestDistribution,
                'wedding_status' => collect(WeddingStatus::values())
                    ->mapWithKeys(fn (string $status) => [$status => (int) ($weddingsByStatus[$status] ?? 0)]),
            ],
        ];
    }
}
