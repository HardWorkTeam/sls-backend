<?php

namespace App\Services;

use App\Models\Wedding;
use Illuminate\Database\Eloquent\Builder;

/**
 * Platform revenue is derived from weddings that have been assigned a package
 * (each such wedding is a package purchase). This service owns those queries so
 * the controller stays thin, matching the rest of the codebase.
 */
class PlatformIncomeService
{
    /**
     * Per-event income rows in the standard {data, links, meta} envelope.
     *
     * @return array<string, mixed>
     */
    public function rows(?string $status, int $perPage): array
    {
        $paginator = $this->baseQuery()
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->latest('weddings.created_at')
            ->paginate($perPage);

        return [
            'data' => $paginator->getCollection()
                ->map(fn (Wedding $wedding) => $this->toRow($wedding))
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
     * Total platform income + per-package breakdown.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $weddings = $this->baseQuery()->get();

        $byPackage = [];
        $total = 0.0;

        foreach ($weddings as $wedding) {
            $amount = (float) ($wedding->package->price ?? 0);
            $total += $amount;

            $key = $wedding->package_id;
            if (! isset($byPackage[$key])) {
                $byPackage[$key] = [
                    'package_id' => $wedding->package_id,
                    'package_name' => $wedding->package->name ?? 'Unknown',
                    'count' => 0,
                    'amount' => 0.0,
                ];
            }
            $byPackage[$key]['count']++;
            $byPackage[$key]['amount'] += $amount;
        }

        return [
            'total_income' => round($total, 2),
            'total_subscriptions' => $weddings->count(),
            'currency' => $weddings->first()?->package?->currency ?? 'USD',
            'by_package' => array_values($byPackage),
        ];
    }

    /**
     * Only weddings that have a package count as platform revenue.
     */
    private function baseQuery(): Builder
    {
        return Wedding::query()
            ->whereNotNull('package_id')
            ->with(['package', 'createdBy']);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Wedding $wedding): array
    {
        return [
            'event_id' => $wedding->id,
            'wedding_code' => $wedding->wedding_code,
            'event_name' => $wedding->wedding_name,
            'event_status' => $wedding->status,
            'user_id' => $wedding->created_by_user_id,
            'user_name' => $wedding->createdBy?->name,
            'package_name' => $wedding->package?->name,
            'amount' => (float) ($wedding->package?->price ?? 0),
            'currency' => $wedding->package?->currency ?? 'USD',
            'purchased_at' => $wedding->created_at?->toIso8601String(),
        ];
    }
}
