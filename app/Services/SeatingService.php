<?php

namespace App\Services;

use App\Models\Wedding;
use App\Models\WeddingTable;
use App\Repositories\GuestRepository;
use App\Repositories\SeatingRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatingService
{
    public function __construct(
        private readonly SeatingRepository $seating,
        private readonly GuestRepository $guests,
    ) {}

    /**
     * @return Collection<int, WeddingTable>
     */
    public function tables(Wedding $wedding): Collection
    {
        return $this->seating->tablesForWedding($wedding);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTable(Wedding $wedding, array $attributes): WeddingTable
    {
        $attributes['wedding_id'] = $wedding->id;

        /** @var WeddingTable $table */
        $table = $this->seating->create($attributes);

        return $table->loadCount('seatings');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateTable(WeddingTable $table, array $attributes): WeddingTable
    {
        $this->seating->update($table, $attributes);

        return $table->loadCount('seatings');
    }

    public function deleteTable(WeddingTable $table): void
    {
        $this->seating->delete($table);
    }

    /**
     * Assign (or move) a guest to a table, enforcing capacity and
     * seat-number uniqueness.
     */
    public function assign(Wedding $wedding, int $guestId, int $tableId, ?int $seatNumber = null): void
    {
        $guest = $this->guests->query()
            ->where('wedding_id', $wedding->id)
            ->findOrFail($guestId);

        /** @var WeddingTable $table */
        $table = $this->seating->query()
            ->where('wedding_id', $wedding->id)
            ->findOrFail($tableId);

        $current = $this->seating->findSeatingForGuest($wedding, $guest->id);
        $occupied = $this->seating->seatedCount($table);

        if ($current?->wedding_table_id !== $table->id && $table->capacity > 0 && $occupied >= $table->capacity) {
            throw ValidationException::withMessages([
                'wedding_table_id' => ["Table \"{$table->table_name}\" is already full ({$table->capacity} seats)."],
            ]);
        }

        $this->seating->assign($wedding, $guest->id, $table->id, $seatNumber);
    }

    public function unassign(Wedding $wedding, int $guestId): void
    {
        $this->seating->findSeatingForGuest($wedding, $guestId)?->delete();
    }

    /**
     * Auto-seat every unassigned guest, filling tables in numeric order
     * while keeping guests of the same group together when possible.
     *
     * @return array{seated: int, unseated: int}
     */
    public function autoSeat(Wedding $wedding): array
    {
        $unseated = $this->guests->unseatedForWedding($wedding);
        $tables = $this->seating->tablesForWedding($wedding);

        $seated = 0;

        DB::transaction(function () use ($wedding, $unseated, $tables, &$seated) {
            $remaining = $tables->map(fn (WeddingTable $table) => (object) [
                'table' => $table,
                'free' => max(($table->capacity ?: PHP_INT_MAX) - $table->seatings->count(), 0),
            ])->filter(fn ($slot) => $slot->free > 0)->values();

            $index = 0;

            foreach ($unseated as $guest) {
                while ($index < $remaining->count() && $remaining[$index]->free === 0) {
                    $index++;
                }

                if ($index >= $remaining->count()) {
                    break;
                }

                $slot = $remaining[$index];
                $this->seating->assign($wedding, $guest->id, $slot->table->id, null);
                $slot->free--;
                $seated++;
            }
        });

        return [
            'seated' => $seated,
            'unseated' => max($unseated->count() - $seated, 0),
        ];
    }

    /**
     * Seating report: occupancy per table plus unassigned guests.
     *
     * @return array<string, mixed>
     */
    public function report(Wedding $wedding): array
    {
        $tables = $this->seating->tablesForWedding($wedding);
        $unseated = $this->guests->unseatedForWedding($wedding);

        return [
            'tables' => $tables->map(fn (WeddingTable $table) => [
                'id' => $table->id,
                'table_name' => $table->table_name,
                'table_number' => $table->table_number,
                'capacity' => $table->capacity,
                'seated' => $table->seatings->count(),
                'available' => $table->capacity > 0
                    ? max($table->capacity - $table->seatings->count(), 0)
                    : null,
            ]),
            'total_capacity' => (int) $tables->sum('capacity'),
            'total_seated' => (int) $tables->sum(fn (WeddingTable $table) => $table->seatings->count()),
            'unseated_guests' => $unseated->count(),
        ];
    }
}
