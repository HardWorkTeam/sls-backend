<?php

namespace App\Services;

use App\Models\Wedding;
use App\Models\WeddingTable;
use App\Repositories\GuestRepository;
use App\Repositories\SeatingRepository;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeatingService
{
    private const EXPORT_COLUMNS = [
        'table_name', 'table_number', 'capacity', 'seated', 'guests',
    ];

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
        DB::transaction(function () use ($wedding, $guestId, $tableId, $seatNumber) {
            $guest = $this->guests->query()
                ->where('wedding_id', $wedding->id)
                ->findOrFail($guestId);

            // Lock the table row so concurrent assigns to the same table
            // serialize — otherwise both pass the capacity check below and
            // overfill it.
            /** @var WeddingTable $table */
            $table = $this->seating->query()
                ->where('wedding_id', $wedding->id)
                ->lockForUpdate()
                ->findOrFail($tableId);

            $current = $this->seating->findSeatingForGuest($wedding, $guest->id);
            $occupied = $this->seating->seatedCount($table);

            if ($current?->wedding_table_id !== $table->id && $table->capacity > 0 && $occupied >= $table->capacity) {
                throw ValidationException::withMessages([
                    'wedding_table_id' => ["Table \"{$table->table_name}\" is already full ({$table->capacity} seats)."],
                ]);
            }

            $this->seating->assign($wedding, $guest->id, $table->id, $seatNumber);
        });
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

    /**
     * Export the wedding's tables as CSV (opens directly in Excel). Each row is
     * a table with its occupancy and the names of the guests seated there.
     */
    public function exportCsv(Wedding $wedding): string
    {
        $tables = $this->seating->tablesForWedding($wedding);

        $handle = fopen('php://temp', 'r+b');
        // BOM so Excel detects UTF-8 (Khmer names, accents, etc.).
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::EXPORT_COLUMNS);

        foreach ($tables as $table) {
            $guestNames = $table->seatings
                ->map(fn ($seating) => $seating->guest?->name)
                ->filter()
                ->implode('; ');

            fputcsv($handle, Csv::row([
                $table->table_name,
                $table->table_number,
                $table->capacity,
                $table->seatings->count(),
                $guestNames,
            ]));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /**
     * Import tables from a CSV file (Excel-compatible). Expected headers:
     * table_name, table_number, capacity. Rows that clash with an existing
     * table name or number are skipped (those columns are unique per wedding).
     *
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importCsv(Wedding $wedding, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Unable to read the uploaded file.']];
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return ['imported' => 0, 'skipped' => 0, 'errors' => ['The file is empty.']];
        }

        // Strip a UTF-8 BOM that Excel prepends to CSV exports.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);

        // Existing names/numbers guard the per-wedding unique constraints so the
        // import never trips a database error mid-file.
        $existingNames = $wedding->tables()->pluck('table_name')
            ->map(fn ($name) => mb_strtolower((string) $name))->all();
        $existingNumbers = $wedding->tables()->whereNotNull('table_number')
            ->pluck('table_number')->map(fn ($number) => (int) $number)->all();

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;

        DB::transaction(function () use ($handle, $header, $wedding, &$existingNames, &$existingNumbers, &$imported, &$skipped, &$errors, &$line) {
            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if (count($row) === 1 && trim((string) $row[0]) === '') {
                    continue;
                }

                // Normalize the row to exactly the header's width — pad short
                // rows, truncate long ones (stray trailing columns from
                // hand-edited files). array_combine throws on a length
                // mismatch, which would 500 the whole import.
                $cells = array_map(fn ($cell) => trim((string) $cell), $row);
                $data = array_combine(
                    $header,
                    array_slice(array_pad($cells, count($header), null), 0, count($header)),
                );

                $name = $data['table_name'] ?? null;

                if (empty($name)) {
                    $skipped++;
                    $errors[] = "Line {$line}: missing table name.";

                    continue;
                }

                if (in_array(mb_strtolower($name), $existingNames, true)) {
                    $skipped++;
                    $errors[] = "Line {$line}: a table named \"{$name}\" already exists.";

                    continue;
                }

                $number = isset($data['table_number']) && $data['table_number'] !== ''
                    ? (int) $data['table_number']
                    : null;

                if ($number !== null && in_array($number, $existingNumbers, true)) {
                    $skipped++;
                    $errors[] = "Line {$line}: table number {$number} is already taken.";

                    continue;
                }

                $capacity = isset($data['capacity']) && $data['capacity'] !== ''
                    ? max(0, (int) $data['capacity'])
                    : 0;

                $wedding->tables()->create([
                    'table_name' => $name,
                    'table_number' => $number,
                    'capacity' => $capacity,
                ]);

                $existingNames[] = mb_strtolower($name);

                if ($number !== null) {
                    $existingNumbers[] = $number;
                }

                $imported++;
            }
        });

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }
}
