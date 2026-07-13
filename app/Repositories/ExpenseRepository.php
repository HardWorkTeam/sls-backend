<?php

namespace App\Repositories;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends EloquentRepository<Expense>
 */
class ExpenseRepository extends EloquentRepository
{
    protected string $modelClass = Expense::class;

    public function searchForWedding(
        Wedding $wedding,
        ?string $status = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->latest('spent_at')
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * @return array{total_expenses: int, total_amount: float, total_paid: float, total_outstanding: float, by_status: array<string, array{count: int, amount: float}>}
     */
    public function summaryForWedding(Wedding $wedding): array
    {
        $rows = $this->query()
            ->where('wedding_id', $wedding->id)
            ->selectRaw('status, currency, count(*) as total, coalesce(sum(amount), 0) as amount, coalesce(sum(paid_amount), 0) as paid')
            ->groupBy('status', 'currency')
            ->get();

        $byStatus = [];
        foreach (ExpenseStatus::cases() as $status) {
            $statusRows = $rows->where('status', $status->value);
            $byStatus[$status->value] = [
                'count' => (int) $statusRows->sum('total'),
                'amount_usd' => (float) $statusRows->where('currency', 'USD')->sum('amount'),
                'amount_khr' => (float) $statusRows->where('currency', 'KHR')->sum('amount'),
            ];
        }

        $totalAmountUsd = (float) $rows->where('currency', 'USD')->sum('amount');
        $totalPaidUsd = (float) $rows->where('currency', 'USD')->sum('paid');

        $totalAmountKhr = (float) $rows->where('currency', 'KHR')->sum('amount');
        $totalPaidKhr = (float) $rows->where('currency', 'KHR')->sum('paid');

        return [
            'total_expenses' => (int) $rows->sum('total'),
            'total_amount_usd' => $totalAmountUsd,
            'total_paid_usd' => $totalPaidUsd,
            'total_outstanding_usd' => round($totalAmountUsd - $totalPaidUsd, 2),
            'total_amount_khr' => $totalAmountKhr,
            'total_paid_khr' => $totalPaidKhr,
            'total_outstanding_khr' => round($totalAmountKhr - $totalPaidKhr, 2),
            'by_status' => $byStatus,
        ];
    }
}
