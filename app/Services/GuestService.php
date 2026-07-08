<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\Wedding;
use App\Repositories\GuestRepository;
use App\Support\Csv;
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
     * Import guests from a CSV file (Excel-compatible).
     * Expected headers: name, phone, email, address, group, is_vip, note.
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

        $groups = $wedding->guestGroups()->pluck('id', 'name');
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $line = 1;

        // Plan guest cap: stop importing once the wedding hits its limit
        // (null = unlimited). Rows beyond the cap are skipped, not created.
        $limit = PlanCapabilities::forWedding($wedding)->guestLimit;
        $remaining = $limit === null ? PHP_INT_MAX : max(0, $limit - $wedding->guests()->count());

        DB::transaction(function () use ($handle, $header, $wedding, $groups, $limit, &$remaining, &$imported, &$skipped, &$errors, &$line) {
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

                if (empty($data['name'])) {
                    $skipped++;
                    $errors[] = "Line {$line}: missing guest name.";

                    continue;
                }

                if ($remaining <= 0) {
                    $skipped++;
                    $errors[] = "Line {$line}: plan limit of {$limit} guests reached.";

                    continue;
                }

                $groupName = $data['group'] ?? null;
                $groupId = null;

                if ($groupName) {
                    $groupId = $groups[$groupName]
                        ?? $wedding->guestGroups()->create(['name' => $groupName])->id;
                    $groups[$groupName] = $groupId;
                }

                $wedding->guests()->create([
                    'name' => $data['name'],
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
        });

        fclose($handle);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Export the wedding's guests as CSV (opens directly in Excel).
     *
     * @param  array{search?: string|null, guest_group_id?: int|null, is_vip?: bool|null}  $filters
     */
    public function exportCsv(Wedding $wedding, array $filters = []): string
    {
        $guests = $this->guests->allForWedding($wedding, $filters);

        $handle = fopen('php://temp', 'r+b');
        // BOM so Excel detects UTF-8 (Khmer names, accents, etc.).
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::EXPORT_COLUMNS);

        foreach ($guests as $guest) {
            fputcsv($handle, Csv::row([
                $guest->name,
                $guest->phone,
                $guest->email,
                $guest->address,
                $guest->group?->name,
                $guest->seating?->table?->table_number,
                $guest->seating?->seat_number,
                $guest->is_vip ? 'yes' : 'no',
                $guest->note,
            ]));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
