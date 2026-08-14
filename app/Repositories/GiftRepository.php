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
        ?string $search = null,
    ): LengthAwarePaginator {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->with('guest')
            ->when($giftType, fn (Builder $query) => $query->where('gift_type', $giftType))
            ->when($search, fn (Builder $query, string $term) => $query->whereHas(
                'guest',
                fn (Builder $guest) => $guest->where('name', 'ilike', "%{$term}%"),
            ))
            ->latest('received_at')
            ->paginate($perPage);
    }

    public function allForWedding(
        Wedding $wedding,
        ?string $giftType = null,
        ?string $search = null,
    ) {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->with('guest')
            ->when($giftType, fn (Builder $query) => $query->where('gift_type', $giftType))
            ->when($search, fn (Builder $query, string $term) => $query->whereHas(
                'guest',
                fn (Builder $guest) => $guest->where('name', 'ilike', "%{$term}%"),
            ))
            ->latest('received_at')
            ->get();
    }

    /**
     * @return array{total_gifts: int, total_cash_amount: float, by_type: array<string, array{count: int, amount: float}>}
     */
    public function summaryForWedding(Wedding $wedding): array
    {
        $rows = $this->query()
            ->where('wedding_id', $wedding->id)
            ->selectRaw('gift_type, currency, count(*) as total, coalesce(sum(amount), 0) as amount')
            ->groupBy('gift_type', 'currency')
            ->get();

        $byType = [];
        foreach (GiftType::cases() as $type) {
            $typeRows = $rows->where('gift_type', $type->value);
            $byType[$type->value] = [
                'count' => (int) $typeRows->sum('total'),
                'amount_usd' => (float) $typeRows->where('currency', 'USD')->sum('amount'),
                'amount_khr' => (float) $typeRows->where('currency', 'KHR')->sum('amount'),
            ];
        }

        return [
            'total_gifts' => (int) $rows->sum('total'),
            'total_cash_amount_usd' => (float) $rows
                ->whereIn('gift_type', [GiftType::Cash->value, GiftType::BankTransfer->value])
                ->where('currency', 'USD')
                ->sum('amount'),
            'total_cash_amount_khr' => (float) $rows
                ->whereIn('gift_type', [GiftType::Cash->value, GiftType::BankTransfer->value])
                ->where('currency', 'KHR')
                ->sum('amount'),
            'by_type' => $byType,
        ];
    }
}
