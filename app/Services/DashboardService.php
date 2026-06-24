<?php

namespace App\Services;

use App\Enums\RsvpStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\WeddingStatus;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\Package;
use App\Models\RsvpResponse;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wedding;
use App\Repositories\WeddingRepository;

class DashboardService
{
    public function __construct(
        private readonly WeddingRepository $weddings,
        private readonly PlatformIncomeService $income,
    ) {}

    /**
     * Super-admin platform analytics: business KPIs + chart datasets across the
     * whole platform (not scoped to a single user's weddings).
     *
     * @return array<string, mixed>
     */
    public function platformAnalytics(): array
    {
        $summary = $this->income->summary();
        $since = now()->subDays(30)->startOfDay();

        $revenueTrend = Subscription::query()
            ->where('status', SubscriptionStatus::Paid->value)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $since)
            ->selectRaw('date(paid_at) as date, sum(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => (string) $row->date, 'total' => round((float) $row->total, 2)]);

        $systemGrowth = User::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('date(created_at) as date, count(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => (string) $row->date, 'total' => (int) $row->total]);

        $templateUsage = Invitation::query()
            ->leftJoin('invitation_templates', 'invitations.invitation_template_id', '=', 'invitation_templates.id')
            ->whereNull('invitations.deleted_at')
            ->selectRaw("coalesce(invitation_templates.name, 'No template') as name, count(*) as total")
            ->groupBy('invitation_templates.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->name, 'total' => (int) $row->total]);

        return [
            'cards' => [
                'total_registered_users' => User::query()->count(),
                'total_weddings_created' => Wedding::query()->count(),
                'total_revenue' => $summary['total_income'],
                'active_selling_packages' => Package::query()->where('is_active', true)->count(),
            ],
            'charts' => [
                'revenue_trend' => $revenueTrend,
                'system_growth' => $systemGrowth,
                'package_sales' => $summary['by_package'],
                'template_usage' => $templateUsage,
            ],
            'currency' => $summary['currency'],
        ];
    }

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
