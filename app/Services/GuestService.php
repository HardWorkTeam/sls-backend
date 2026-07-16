<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Wedding;
use App\Repositories\GuestRepository;
use App\Support\Csv;
use App\Support\Excel;
use App\Support\PlanCapabilities;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class GuestService
{
    private const EXPORT_COLUMNS = [
        'name', 'phone', 'email', 'address', 'group', 'table_number', 'seat_number', 'is_vip', 'note',
    ];

    public function __construct(private readonly GuestRepository $guests) {}

    /**
     * @param  array{search?: string|null, guest_group_id?: int|null, is_vip?: bool|null}  $filters
     */
    public function list(Wedding $wedding, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->guests->searchForWedding($wedding, $filters, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Wedding $wedding, array $attributes): Guest
    {
        $this->assertGuestCapacity($wedding);

        $attributes['wedding_id'] = $wedding->id;

        /** @var Guest $guest */
        $guest = $this->guests->create($attributes);

        return $guest->load(['group', 'invitation', 'seating.table']);
    }

    /**
     * Guard the wedding's plan guest cap before adding $adding more guests.
     * Unlimited plans (null limit) never block.
     */
    private function assertGuestCapacity(Wedding $wedding, int $adding = 1): void
    {
        $limit = PlanCapabilities::forWedding($wedding)->guestLimit;

        if ($limit === null) {
            return;
        }

        $current = $wedding->guests()->count();

        abort_if(
            $current + $adding > $limit,
            422,
            "Your plan allows up to {$limit} guests. Upgrade your plan to add more.",
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Guest $guest, array $attributes): Guest
    {
        $this->guests->update($guest, $attributes);

        return $guest->load(['group', 'invitation', 'seating.table']);
    }

    public function delete(Guest $guest): void
    {
        $this->guests->delete($guest);
    }

    /**
     * Check a guest in by their QR token (wedding-day scan). The token is
     * scoped to the wedding so a code from another event never matches.
     *
     * @return array{guest: Guest, already_checked_in: bool}
     */
    public function checkInByToken(Wedding $wedding, string $token): array
    {
        $guest = $this->guests->findByToken($wedding, $token);

        abort_if($guest === null, 404, 'No guest matches this code for this wedding.');

        $already = $guest->isCheckedIn();

        if (! $already) {
            // Atomic claim: two door staff scanning the same code concurrently
            // race the read above, so let the database decide who was first.
            $claimed = $this->guests->query()
                ->whereKey($guest->id)
                ->whereNull('checked_in_at')
                ->update(['checked_in_at' => now()]);

            $already = $claimed === 0;
            $guest->refresh();
        }

        return [
            'guest' => $guest->load(['group', 'invitation', 'seating.table']),
            'already_checked_in' => $already,
        ];
    }

    /**
     * Manually mark a guest as arrived / not arrived from the guest list.
     */
    public function setCheckIn(Guest $guest, bool $arrived): Guest
    {
        $this->guests->update($guest, ['checked_in_at' => $arrived ? now() : null]);

        return $guest->load(['group', 'invitation', 'seating.table']);
    }

    /**
     * Render a guest's check-in QR code (their short code) as an SVG. Printed
     * onto the invitation and scanned at the door on the wedding day. Encodes
     * the same short code shown as text so scanning and manual entry resolve
     * identically.
     */
    public function qrCodeSvg(Guest $guest, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString(
            (string) ($guest->check_in_code ?? $guest->check_in_token),
        );
    }

    /**
     * Arrival tally for the wedding-day check-in dashboard.
     *
     * @return array{total: int, arrived: int, pending: int}
     */
    public function checkInStats(Wedding $wedding): array
    {
        $total = $wedding->guests()->count();
        $arrived = $wedding->guests()->whereNotNull('checked_in_at')->count();

        return [
            'total' => $total,
            'arrived' => $arrived,
            'pending' => $total - $arrived,
        ];
    }

    /**
     * Attach an invitation to many guests at once (bulk invite).
     *
     * @param  list<int>  $guestIds
     */
    public function bulkInvite(Wedding $wedding, array $guestIds, int $invitationId): int
    {
        return Guest::query()
            ->where('wedding_id', $wedding->id)
            ->whereIn('id', $guestIds)
            ->update(['invitation_id' => $invitationId]);
    }

    /**
     * Import guests from an Excel (XLSX/XLS) or CSV file across all sheets.
     * Uses each sheet tab name as the default group name (e.g. "Family", "VIP"),
     * unless overridden by a 'group' column value in the row.
     * Supports single-column lists, numbered lists ("1. Guest Name", "2. Serey & Maramony"),
     * index/sequence columns ("No.", "#", "ល.រ"), flexible headers in English & Khmer,
     * as well as completely headerless guest lists.
     *
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importCsv(Wedding $wedding, UploadedFile $file): array
    {
        $sheets = Excel::readSheets($file->getRealPath());

        if ($sheets === null) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Unable to read the uploaded file.']];
        }

        $aliases = [
            'name' => [
                'name', 'guest_name', 'full_name', 'guest', 'names', 'guest name', 'full name', 'fullname',
                'guests', 'guestlist', 'guest list', 'khmer name', 'english name', 'attendee', 'attendees',
                'visitor', 'visitors', 'ឈ្មោះ', 'ភ្ញៀវ', 'ឈ្មោះភ្ញៀវ', 'ឈ្មោះ ភ្ញៀវ', 'អ្នកចូលរួម', 'ឈ្មោះពេញ',
            ],
            'phone' => ['phone', 'tel', 'phone_number', 'mobile', 'contact', 'phone number', 'phonenumber', 'លេខទូរស័ព្ទ', 'ទូរស័ព្ទ', 'លេខទូរសព្ទ'],
            'email' => ['email', 'email_address', 'mail', 'email address', 'អ៊ីមែល'],
            'address' => ['address', 'location', 'addr', 'អាសយដ្ឋាន', 'ទីតាំង'],
            'group' => ['group', 'guest_group', 'category', 'type', 'guest group', 'group_name', 'group name', 'ក្រុម', 'ប្រភេទ'],
            'is_vip' => ['is_vip', 'vip', 'is vip', 'វីអាយភី'],
            'note' => ['note', 'notes', 'remark', 'remarks', 'comment', 'comments', 'ចំណាំ', 'ផ្សេងៗ'],
        ];

        $sequenceAliases = [
            'no', 'no.', 'nº', '#', 'id', 'item', 'num', 'number', 'seq', 'index',
            'ល.រ', 'លរ', 'លរ.', 'ល.រ.', 'លំដាប់', 'លំដាប់លេខ',
        ];

        $groups = $wedding->guestGroups()->pluck('id', 'name')->toArray();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Plan guest cap: stop importing once the wedding hits its limit (null = unlimited).
        $limit = PlanCapabilities::forWedding($wedding)->guestLimit;
        $remaining = $limit === null ? PHP_INT_MAX : max(0, $limit - $wedding->guests()->count());

        DB::transaction(function () use ($sheets, $aliases, $sequenceAliases, $wedding, &$groups, $limit, &$remaining, &$imported, &$skipped, &$errors) {
            foreach ($sheets as $sheet) {
                $rawSheetName = $sheet['name'];
                // Ignore generic sheet names like Sheet1, Worksheet 1
                $isGenericSheet = (bool) preg_match('/^(sheet|table|worksheet|page)\s*\d*$/i', $rawSheetName);
                $defaultSheetGroup = $isGenericSheet ? null : $rawSheetName;

                $header = $sheet['header'];
                $rows = $sheet['rows'];

                // Build column map: map index in row -> canonical key ('name', 'phone', etc.)
                $columnMap = [];
                $hasNameColumn = false;

                foreach ($header as $colIndex => $colText) {
                    $colTextClean = trim((string) $colText);
                    if ($colTextClean === '') {
                        continue;
                    }

                    $colTextLower = mb_strtolower($colTextClean);

                    if (in_array($colTextLower, $sequenceAliases, true)) {
                        $columnMap[$colIndex] = '__seq__';
                        continue;
                    }

                    foreach ($aliases as $key => $keywords) {
                        if (in_array($colTextLower, $keywords, true)) {
                            $columnMap[$colIndex] = $key;
                            if ($key === 'name') {
                                $hasNameColumn = true;
                            }
                            break;
                        }
                    }
                }

                // Determine if header row is actually data (headerless file)
                $firstColIsNumber = isset($header[0]) && (bool) preg_match('/^(?:#?\d+[\.\)\-\/\:]?|\d+)$/u', trim((string) $header[0]));
                $nonSeqMapped = array_filter($columnMap, fn ($k) => $k !== '__seq__');

                if (count($nonSeqMapped) === 0 || $firstColIsNumber) {
                    // Header is actually data! Re-insert header into rows.
                    array_unshift($rows, $header);
                    $columnMap = [];
                    $hasNameColumn = true;

                    // Heuristic for headerless rows:
                    // If first row has 2+ columns and Col 0 is sequence number (like "1" or "1."), Col 1 is name.
                    // Otherwise Col 0 is name.
                    $sampleRow = $rows[0] ?? [];
                    $sampleCol0 = trim((string) ($sampleRow[0] ?? ''));
                    $sampleCol0IsSeq = (bool) preg_match('/^(?:#?\d+[\.\)\-\/\:]?|\d+)$/u', $sampleCol0);

                    if (count($sampleRow) > 1 && $sampleCol0IsSeq) {
                        $columnMap[1] = 'name';
                        $columnMap[0] = '__seq__';
                        $columnMap[2] = 'phone';
                        $columnMap[3] = 'email';
                        $columnMap[4] = 'address';
                        $columnMap[5] = 'group';
                        $columnMap[6] = 'is_vip';
                        $columnMap[7] = 'note';
                    } else {
                        $columnMap[0] = 'name';
                        $columnMap[1] = 'phone';
                        $columnMap[2] = 'email';
                        $columnMap[3] = 'address';
                        $columnMap[4] = 'group';
                        $columnMap[5] = 'is_vip';
                        $columnMap[6] = 'note';
                    }
                } elseif (! $hasNameColumn) {
                    // Mapped headers existed (e.g. sequence column, phone, etc.), but no explicit 'name' alias matched
                    $seqColIndex = array_search('__seq__', $columnMap, true);
                    if ($seqColIndex !== false && isset($header[$seqColIndex + 1])) {
                        $columnMap[$seqColIndex + 1] = 'name';
                    } elseif (isset($header[0]) && ($columnMap[0] ?? null) !== '__seq__') {
                        $columnMap[0] = 'name';
                    } else {
                        $columnMap[1] = 'name';
                    }
                }

                $line = 1; // row 1 was header or first data line

                foreach ($rows as $row) {
                    $line++;
                    $sheetLabel = count($sheets) > 1 ? "Sheet \"{$rawSheetName}\", Line {$line}" : "Line {$line}";

                    // Map row data using columnMap
                    $data = [];
                    foreach ($columnMap as $colIndex => $key) {
                        if ($key === '__seq__') {
                            continue;
                        }
                        $val = isset($row[$colIndex]) ? trim((string) $row[$colIndex]) : '';
                        if ($val !== '') {
                            $data[$key] = $val;
                        }
                    }

                    $guestName = $data['name'] ?? null;

                    if ($guestName !== null) {
                        // Clean up leading sequence numbers (e.g. "1. Chan Vireakboth", "2. Serey & Maramony", "3) Sok", "#4 John")
                        $guestName = trim(preg_replace('/^(?:#?\d+[\.\)\-\/\:]\s*|#?\d+\s+)/u', '', $guestName));
                    }

                    if (! $guestName) {
                        // Skip if blank row
                        $isRowEmpty = count(array_filter($row, fn ($cell) => $cell !== null && trim((string) $cell) !== '')) === 0;
                        if (! $isRowEmpty) {
                            $skipped++;
                            $errors[] = "{$sheetLabel}: missing guest name.";
                        }

                        continue;
                    }

                    if ($remaining <= 0) {
                        $skipped++;
                        $errors[] = "{$sheetLabel}: plan limit of {$limit} guests reached.";

                        continue;
                    }

                    $groupName = $data['group'] ?? $defaultSheetGroup;
                    $groupId = null;

                    if ($groupName) {
                        if (isset($groups[$groupName])) {
                            $groupId = $groups[$groupName];
                        } else {
                            $newGroup = $wedding->guestGroups()->create(['name' => $groupName]);
                            $groupId = $newGroup->id;
                            $groups[$groupName] = $groupId;
                        }
                    }

                    $wedding->guests()->create([
                        'name' => $guestName,
                        'phone' => $data['phone'] ?? null,
                        'email' => $data['email'] ?? null,
                        'address' => $data['address'] ?? null,
                        'note' => $data['note'] ?? null,
                        'is_vip' => filter_var($data['is_vip'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'guest_group_id' => $groupId,
                    ]);

                    $imported++;
                    $remaining--;
                }
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Export the wedding's guests as a formatted Excel (.xlsx) file.
     *
     * @param  array{search?: string|null, guest_group_id?: int|null, is_vip?: bool|null}  $filters
     */
    public function exportExcel(Wedding $wedding, array $filters = []): string
    {
        $guests = $this->guests->allForWedding($wedding, $filters);

        $rows = [];
        foreach ($guests as $guest) {
            $rows[] = [
                $guest->name,
                $guest->phone,
                $guest->email,
                $guest->address,
                $guest->group?->name,
                $guest->seating?->table?->table_number,
                $guest->seating?->seat_number,
                $guest->is_vip ? 'Yes' : 'No',
                $guest->note,
            ];
        }

        return Excel::build(
            ['Name', 'Phone', 'Email', 'Address', 'Group', 'Table Number', 'Seat Number', 'VIP', 'Note'],
            $rows,
            'Guests',
        );
    }
}
