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
     * Supports flexible column names (Name, Phone, Email, Address, Group, VIP, Note)
     * as well as headerless guest lists.
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
            'name' => ['name', 'guest_name', 'full_name', 'guest', 'names', 'guest name', 'full name', 'fullname'],
            'phone' => ['phone', 'tel', 'phone_number', 'mobile', 'contact', 'phone number', 'phonenumber'],
            'email' => ['email', 'email_address', 'mail', 'email address'],
            'address' => ['address', 'location', 'addr'],
            'group' => ['group', 'guest_group', 'category', 'type', 'guest group', 'group_name', 'group name'],
            'is_vip' => ['is_vip', 'vip', 'is vip'],
            'note' => ['note', 'notes', 'remark', 'remarks', 'comment', 'comments'],
        ];

        $groups = $wedding->guestGroups()->pluck('id', 'name')->toArray();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Plan guest cap: stop importing once the wedding hits its limit (null = unlimited).
        $limit = PlanCapabilities::forWedding($wedding)->guestLimit;
        $remaining = $limit === null ? PHP_INT_MAX : max(0, $limit - $wedding->guests()->count());

        DB::transaction(function () use ($sheets, $aliases, $wedding, &$groups, $limit, &$remaining, &$imported, &$skipped, &$errors) {
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
                    foreach ($aliases as $key => $keywords) {
                        if (in_array($colTextClean, $keywords, true)) {
                            $columnMap[$colIndex] = $key;
                            if ($key === 'name') {
                                $hasNameColumn = true;
                            }
                            break;
                        }
                    }
                }

                // If no column matched any header alias, the first row is actually data (headerless file)
                if (count($columnMap) === 0) {
                    array_unshift($rows, $header);
                    $columnMap = [0 => 'name', 1 => 'phone', 2 => 'email', 3 => 'address', 4 => 'group', 5 => 'is_vip', 6 => 'note'];
                    $hasNameColumn = true;
                } elseif (! $hasNameColumn && isset($header[0])) {
                    // Default column 0 to name if no explicit 'name' header was matched
                    $columnMap[0] = 'name';
                }

                $line = 1; // row 1 was header or first data line

                foreach ($rows as $row) {
                    $line++;
                    $sheetLabel = count($sheets) > 1 ? "Sheet \"{$rawSheetName}\", Line {$line}" : "Line {$line}";

                    // Map row data using columnMap
                    $data = [];
                    foreach ($columnMap as $colIndex => $key) {
                        $val = isset($row[$colIndex]) ? trim((string) $row[$colIndex]) : '';
                        if ($val !== '') {
                            $data[$key] = $val;
                        }
                    }

                    $guestName = $data['name'] ?? null;

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
