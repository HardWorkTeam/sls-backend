<?php

namespace App\Repositories;

use App\Enums\GiftType;
use App\Models\Gift;
use App\Models\Wedding;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends EloquentRepository<Gift>
 */
class GiftRepository extends EloquentRepository
{
    protected string $modelClass = Gift::class;

    public function searchForWedding(
        Wedding $wedding,
        ?string $giftType = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->with('guest')
            ->when($giftType, fn (Builder $query) => $query->where('gift_type', $giftType))
            ->latest('received_at')
            ->paginate($perPage);
    }

    /**
     * @return array{total_gifts: int, total_cash_amount: float, by_type: array<string, array{count: int, amount: float}>}
     */
    public function summaryForWedding(Wedding $wedding): array
    {
        $rows = $this->query()
            ->where('wedding_id', $wedding->id)
            ->selectRaw('gift_type, count(*) as total, coalesce(sum(amount), 0) as amount')
            ->groupBy('gift_type')
            ->get();

        $byType = [];
        foreach (GiftType::cases() as $type) {
            $row = $rows->firstWhere('gift_type', $type->value);
            $byType[$type->value] = [
                'count' => (int) ($row->total ?? 0),
                'amount' => (float) ($row->amount ?? 0),
            ];
        }

        return [
            'total_gifts' => (int) $rows->sum('total'),
            'total_cash_amount' => (float) $rows
                ->whereIn('gift_type', [GiftType::Cash->value, GiftType::BankTransfer->value])
                ->sum('amount'),
            'by_type' => $byType,
        ];
    }
}
