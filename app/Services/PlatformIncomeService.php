<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;

/**
 * Platform revenue = subscriptions that have been PAID (admin-confirmed). Each
 * paid subscription is real, received income. This service owns those queries
 * so the controller stays thin, matching the rest of the codebase.
 */
class PlatformIncomeService
{
    /**
     * Per-payment income rows in the standard {data, links, meta} envelope.
     *
     * @return array<string, mixed>
     */
    public function rows(?string $status, int $perPage): array
    {
        $paginator = $this->baseQuery()
            ->when($status, fn (Builder $query) => $query->whereHas(
                'wedding',
                fn (Builder $wedding) => $wedding->where('status', $status),
            ))
            ->latest('paid_at')
            ->paginate($perPage);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (Subscription $subscription) => $this->toRow($subscription))
                ->all(),
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Total platform income (paid only) + per-package breakdown.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $subscriptions = $this->baseQuery()->get();

        $byPackage = [];
        $total = 0.0;

        foreach ($subscriptions as $subscription) {
            $amount = (float) $subscription->amount;
            $total += $amount;

            // Key on both package_id and currency so mixed-currency packages
            // are never combined into a single inaccurate total.
            $key = ($subscription->package_id ?? 0).'_'.$subscription->currency;
            if (! isset($byPackage[$key])) {
                $byPackage[$key] = [
                    'package_id'   => $subscription->package_id,
                    'package_name' => $subscription->package?->name ?? 'Unknown',
                    'currency'     => $subscription->currency,
                    'count'        => 0,
                    'amount'       => 0.0,
                ];
            }
            $byPackage[$key]['count']++;
            $byPackage[$key]['amount'] += $amount;
        }

        return [
            'total_income' => round($total, 2),
            'total_subscriptions' => $subscriptions->count(),
            'currency' => $subscriptions->first()?->currency ?? 'USD',
            'by_package' => array_values($byPackage),
        ];
    }

    /**
     * Only PAID subscriptions count as platform revenue.
     */
    private function baseQuery(): Builder
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Paid->value)
            ->with(['package', 'wedding.createdBy']);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Subscription $subscription): array
    {
        $wedding = $subscription->wedding;

        return [
            'id'           => $subscription->id,
            'event_id'     => $wedding?->id,
            'wedding_code' => $wedding?->wedding_code,
            'event_name'   => $wedding?->wedding_name,
            'event_status' => $wedding?->status,
            'user_id'      => $wedding?->created_by_user_id,
            'user_name'    => $wedding?->createdBy?->name,
            'package_name' => $subscription->package?->name,
            'amount'       => (float) $subscription->amount,
            'currency'     => $subscription->currency,
            'purchased_at' => $subscription->paid_at?->toIso8601String(),
        ];
    }
}
