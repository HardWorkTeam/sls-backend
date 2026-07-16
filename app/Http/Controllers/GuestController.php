<?php

namespace App\Http\Controllers;

use App\Http\Requests\Guest\BulkInviteRequest;
use App\Http\Requests\Guest\CheckInRequest;
use App\Http\Requests\Guest\ImportGuestsRequest;
use App\Http\Requests\Guest\StoreGuestRequest;
use App\Http\Requests\Guest\UpdateGuestRequest;
use App\Http\Resources\GuestResource;
use App\Models\Guest;
use App\Models\Wedding;
use App\Services\GuestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class GuestController extends Controller
{
    public function __construct(private readonly GuestService $guestService) {}

    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        $guests = $this->guestService->list(
            $wedding,
            [
                'search' => $request->query('search'),
                'guest_group_id' => $request->query('guest_group_id'),
                'is_vip' => $request->has('is_vip') ? $request->boolean('is_vip') : null,
            ],
            (int) $request->query('per_page', '15'),
        );

        return GuestResource::collection($guests);
    }

    public function store(StoreGuestRequest $request, Wedding $wedding): JsonResponse
    {
        $guest = $this->guestService->create($wedding, $request->validated());

        return GuestResource::make($guest)->response()->setStatusCode(201);
    }

    public function update(UpdateGuestRequest $request, Wedding $wedding, Guest $guest): GuestResource
    {
        abort_unless($guest->wedding_id === $wedding->id, 404);

        return GuestResource::make($this->guestService->update($guest, $request->validated()));
    }

    public function destroy(Wedding $wedding, Guest $guest): JsonResponse
    {
        abort_unless($guest->wedding_id === $wedding->id, 404);

        $this->guestService->delete($guest);

        return response()->json(['message' => 'Guest deleted.']);
    }

    public function destroyAll(Wedding $wedding): JsonResponse
    {
        $count = $this->guestService->deleteAll($wedding);

        return response()->json([
            'message' => "All {$count} guests have been erased.",
            'deleted' => $count,
        ]);
    }

    public function import(ImportGuestsRequest $request, Wedding $wedding): JsonResponse
    {
        $result = $this->guestService->importCsv($wedding, $request->file('file'));

        return response()->json([
            'message' => "Imported {$result['imported']} guests ({$result['skipped']} skipped).",
            'data' => $result,
        ]);
    }

    public function export(Request $request, Wedding $wedding): Response
    {
        $xlsx = $this->guestService->exportExcel($wedding, [
            'search' => $request->query('search'),
            'guest_group_id' => $request->query('guest_group_id'),
        ]);

        $safeName = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $wedding->wedding_name ?? '');
        $safeName = trim(preg_replace('/\s+/', '_', $safeName));
        $filename = $safeName
            ? "guests-{$safeName}-{$wedding->wedding_code}.xlsx"
            : "guests-{$wedding->wedding_code}.xlsx";

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function bulkInvite(BulkInviteRequest $request, Wedding $wedding): JsonResponse
    {
        $updated = $this->guestService->bulkInvite(
            $wedding,
            $request->validated('guest_ids'),
            (int) $request->validated('invitation_id'),
        );

        return response()->json(['message' => "Invitation attached to {$updated} guests."]);
    }

    /**
     * Render a guest's personal check-in QR code as an SVG (for printing onto
     * the invitation).
     */
    public function qrCode(Wedding $wedding, Guest $guest): Response
    {
        abort_unless($guest->wedding_id === $wedding->id, 404);

        return response($this->guestService->qrCodeSvg($guest), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => "inline; filename=\"guest-{$guest->id}-qr.svg\"",
        ]);
    }

    /**
     * Wedding-day check-in: resolve a scanned QR token and mark the guest as
     * arrived. Returns the guest plus whether they were already checked in.
     */
    public function checkIn(CheckInRequest $request, Wedding $wedding): JsonResponse
    {
        $result = $this->guestService->checkInByToken($wedding, $request->validated('token'));

        return response()->json([
            'data' => GuestResource::make($result['guest']),
            'already_checked_in' => $result['already_checked_in'],
        ]);
    }

    /**
     * Manually flip a guest's arrival status from the guest list.
     */
    public function setCheckIn(Wedding $wedding, Guest $guest, Request $request): GuestResource
    {
        abort_unless($guest->wedding_id === $wedding->id, 404);

        return GuestResource::make(
            $this->guestService->setCheckIn($guest, $request->boolean('arrived', true)),
        );
    }

    public function checkInStats(Wedding $wedding): JsonResponse
    {
        return response()->json(['data' => $this->guestService->checkInStats($wedding)]);
    }
}
