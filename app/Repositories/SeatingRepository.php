<?php

namespace App\Repositories;

use App\Models\GuestSeating;
use App\Models\Wedding;
use App\Models\WeddingTable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<WeddingTable>
 */
class SeatingRepository extends EloquentRepository
{
    protected string $modelClass = WeddingTable::class;

    /**
     * @return Collection<int, WeddingTable>
     */
    public function tablesForWedding(Wedding $wedding): Collection
    {
        return $this->query()
            ->where('wedding_id', $wedding->id)
            ->with(['seatings.guest.group'])
            ->withCount('seatings')
            ->orderBy('table_number')
            ->orderBy('table_name')
            ->get();
    }

    public function findSeatingForGuest(Wedding $wedding, int $guestId): ?GuestSeating
    {
        return GuestSeating::query()
            ->where('wedding_id', $wedding->id)
            ->where('guest_id', $guestId)
            ->first();
    }

    public function assign(Wedding $wedding, int $guestId, int $tableId, ?int $seatNumber): GuestSeating
    {
        return GuestSeating::query()->updateOrCreate(
            ['wedding_id' => $wedding->id, 'guest_id' => $guestId],
            ['wedding_table_id' => $tableId, 'seat_number' => $seatNumber],
        );
    }

    public function seatedCount(WeddingTable $table): int
    {
        return GuestSeating::query()->where('wedding_table_id', $table->id)->count();
    }
}
