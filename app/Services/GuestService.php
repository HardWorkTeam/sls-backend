<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Wedding;
use App\Repositories\GuestRepository;
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
    public function __construct(
        private readonly GuestRepository $guests,
        private readonly InvitationService $invitationService,
    ) {}

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
     * Delete all or specific selected guests for a wedding.
     *
     * @param  list<int>|null  $guestIds
     */
    public function deleteAll(Wedding $wedding, ?array $guestIds = null): int
    {
        $query = $wedding->guests();

        if (! empty($guestIds)) {
            $query->whereIn('id', $guestIds);
        }

        return $query->delete();
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
     * offset columns (e.g. Column A empty, Col B sequence number, Col C guest name),
     * title banner rows ("ឈ្មោះភ្ញៀវ..."), flexible headers in English & Khmer,
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

                // Collect all non-empty rows in this sheet
                $rawRows = array_merge([$sheet['header']], $sheet['rows']);
                $allRows = [];
                foreach ($rawRows as $row) {
                    $hasCell = false;
                    foreach ($row as $cell) {
                        if ($cell !== null && trim((string) $cell) !== '') {
                            $hasCell = true;
                            break;
                        }
                    }
                    if ($hasCell) {
                        $allRows[] = array_map(fn ($cell) => $cell !== null ? trim((string) $cell) : '', $row);
                    }
                }

                if (count($allRows) === 0) {
                    continue;
                }

                // 1. Check if the first row is a title banner (e.g. "ឈ្មោះភ្ញៀវខាងប៉ាកូនកំលោះ")
                $firstRow = $allRows[0];
                $nonEmptyCellsInFirstRow = array_values(array_filter($firstRow, fn ($c) => $c !== ''));

                $isTitleBanner = false;
                if (count($nonEmptyCellsInFirstRow) <= 2) {
                    $firstRowText = implode(' ', $nonEmptyCellsInFirstRow);
                    if (preg_match('/(?:ឈ្មោះភ្ញៀវ|បញ្ជីឈ្មោះ|guest\s*list|list\s*of\s*guests)/iu', $firstRowText)) {
                        $isTitleBanner = true;
                    }
                }

                if ($isTitleBanner) {
                    $titleText = trim(implode(' ', $nonEmptyCellsInFirstRow));
                    if (! $defaultSheetGroup && $titleText !== '') {
                        $defaultSheetGroup = $titleText;
                    }
                    array_shift($allRows); // Remove title banner row
                }

                if (count($allRows) === 0) {
                    continue;
                }

                // 2. Determine column mapping
                $columnMap = [];
                $hasExplicitNameHeader = false;
                $possibleHeaderRow = $allRows[0];

                foreach ($possibleHeaderRow as $colIndex => $colTextClean) {
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
                                $hasExplicitNameHeader = true;
                            }
                            break;
                        }
                    }
                }

                $mappedKeys = array_values(array_filter($columnMap, fn ($k) => $k !== '__seq__'));
                $isExplicitHeaderRow = count($mappedKeys) > 0 || in_array('__seq__', $columnMap, true);

                // If explicit header row matched, shift it out of data rows
                if ($isExplicitHeaderRow && $hasExplicitNameHeader) {
                    array_shift($allRows);
                } else {
                    // No explicit 'name' header matched, or row 0 is actual data rows.
                    // If sequence header was matched on row 0, shift header out; otherwise keep row 0 as data.
                    if ($isExplicitHeaderRow && ! $hasExplicitNameHeader) {
                        array_shift($allRows);
                    }

                    // Perform data-driven column classification across data rows
                    $colStats = [];
                    foreach ($allRows as $r) {
                        foreach ($r as $colIdx => $val) {
                            if ($val === '') {
                                continue;
                            }
                            if (! isset($colStats[$colIdx])) {
                                $colStats[$colIdx] = ['seq' => 0, 'phone' => 0, 'email' => 0, 'text' => 0, 'total' => 0];
                            }
                            $colStats[$colIdx]['total']++;

                            if (preg_match('/^(?:#?\d+[\.\)\-\/\:]?|\d+)$/u', $val)) {
                                $colStats[$colIdx]['seq']++;
                            } elseif (preg_match('/^\+?\d[\d\s\-\.]{6,14}$/', $val)) {
                                $colStats[$colIdx]['phone']++;
                            } elseif (str_contains($val, '@')) {
                                $colStats[$colIdx]['email']++;
                            } else {
                                $colStats[$colIdx]['text']++;
                            }
                        }
                    }

                    // Map sequence, phone, email columns based on stats
                    foreach ($colStats as $colIdx => $stats) {
                        if (isset($columnMap[$colIdx])) {
                            continue;
                        }
                        if ($stats['seq'] / max(1, $stats['total']) > 0.4) {
                            $columnMap[$colIdx] = '__seq__';
                        } elseif ($stats['phone'] / max(1, $stats['total']) > 0.4) {
                            $columnMap[$colIdx] = 'phone';
                        } elseif ($stats['email'] / max(1, $stats['total']) > 0.4) {
                            $columnMap[$colIdx] = 'email';
                        }
                    }

                    // Find the best 'name' column: unmapped column with the highest text count
                    $bestNameCol = null;
                    $maxText = -1;
                    foreach ($colStats as $colIdx => $stats) {
                        if (isset($columnMap[$colIdx])) {
                            continue;
                        }
                        if ($stats['text'] > $maxText) {
                            $maxText = $stats['text'];
                            $bestNameCol = $colIdx;
                        }
                    }

                    if ($bestNameCol !== null) {
                        $columnMap[$bestNameCol] = 'name';
                    } else {
                        // Fallback: first non-sequence column is name
                        foreach ($colStats as $colIdx => $stats) {
                            if (($columnMap[$colIdx] ?? null) !== '__seq__') {
                                $columnMap[$colIdx] = 'name';
                                break;
                            }
                        }
                    }
                }

                $line = 1;

                foreach ($allRows as $row) {
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
        // The invite-link column is the only consumer of the invitation, so it
        // is loaded here rather than widening allForWedding for every caller.
        $guests->loadMissing('invitation');

        $canCheckIn = PlanCapabilities::forWedding($wedding)->checkin;

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
                $this->inviteLink($guest, $canCheckIn),
            ];
        }

        return Excel::build(
            ['Name', 'Phone', 'Email', 'Address', 'Group', 'Table Number', 'Seat Number', 'VIP', 'Note', 'Invite Link'],
            $rows,
            'Guests',
        );
    }

    /**
     * Personal invitation link for one guest: the invitation's public URL plus
     * `?to=` (greets them by name on the invite) and `?t=` (their short
     * check-in code, which powers the "my check-in QR" pass). Null when no
     * invitation is attached. Mirrors the per-guest copy button in the portal
     * guest list — keep the two in step.
     */
    private function inviteLink(Guest $guest, bool $canCheckIn): ?string
    {
        if ($guest->invitation === null) {
            return null;
        }

        $query = ['to' => $guest->name];

        if ($canCheckIn && $guest->check_in_code) {
            $query['t'] = $guest->check_in_code;
        }

        return $this->invitationService->publicUrl($guest->invitation)
            .'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
