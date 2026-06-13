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
            ->selectRaw('status, count(*) as total, coalesce(sum(amount), 0) as amount, coalesce(sum(paid_amount), 0) as paid')
            ->groupBy('status')
            ->get();

        $byStatus = [];
        foreach (ExpenseStatus::cases() as $status) {
            $row = $rows->firstWhere('status', $status->value);
            $byStatus[$status->value] = [
                'count' => (int) ($row->total ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ];
        }

        $totalAmount = (float) $rows->sum('amount');
        $totalPaid = (float) $rows->sum('paid');

        return [
            'total_expenses' => (int) $rows->sum('total'),
            'total_amount' => $totalAmount,
            'total_paid' => $totalPaid,
            'total_outstanding' => round($totalAmount - $totalPaid, 2),
            'by_status' => $byStatus,
        ];
    }
}
