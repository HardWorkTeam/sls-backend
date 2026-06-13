<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomeController extends Controller
{
    /**
     * Per-event platform income rows. Each wedding that has been assigned a
     * package represents a package purchase (platform revenue).
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $paginator = $this->baseQuery()
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->latest('weddings.created_at')
            ->paginate((int) $request->query('per_page', '15'));

        $rows = $paginator->getCollection()
            ->map(fn (Wedding $wedding) => $this->toRow($wedding))
            ->all();

        return response()->json([
            'data' => $rows,
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
        ]);
    }

    /**
     * Summary cards: total platform income and a per-package breakdown.
     */
    public function summary(): JsonResponse
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

        return response()->json([
            'data' => [
                'total_income' => round($total, 2),
                'total_subscriptions' => $weddings->count(),
                'currency' => $weddings->first()?->package?->currency ?? 'USD',
                'by_package' => array_values($byPackage),
            ],
        ]);
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
