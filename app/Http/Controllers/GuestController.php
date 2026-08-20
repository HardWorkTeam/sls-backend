<?php

namespace App\Http\Controllers;

use App\Http\Requests\Guest\BulkGroupRequest;
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
use Symfony\Component\HttpFoundation\HeaderUtils;

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
                'sort' => $request->query('sort'),
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
        return GuestResource::make($this->guestService->update($guest, $request->validated()));
    }

    public function destroy(Wedding $wedding, Guest $guest): JsonResponse
    {
        $this->guestService->delete($guest);

        return response()->json(['message' => 'Guest deleted.']);
    }

    public function destroyAll(Request $request, Wedding $wedding): JsonResponse
    {
        $rawIds = $request->input('guest_ids');
        $guestIds = is_array($rawIds) && count($rawIds) > 0
            ? array_values(array_map('intval', array_filter($rawIds, 'is_numeric')))
            : null;

        $count = $this->guestService->deleteAll($wedding, $guestIds);

        return response()->json([
            'message' => "{$count} guests have been deleted.",
            'deleted' => $count,
        ]);
    }

    public function importPreview(ImportGuestsRequest $request, Wedding $wedding): JsonResponse
    {
        $result = $this->guestService->previewImportCsv($wedding, $request->file('file'));

        return response()->json([
            'message' => "Successfully parsed file.",
            'data' => $result,
        ]);
    }

    public function importConfirm(Request $request, Wedding $wedding): JsonResponse
    {
        $validated = $request->validate([
            'guests' => ['required', 'array'],
            'guests.*.name' => ['required', 'string'],
            'guests.*.phone' => ['nullable', 'string'],
            'guests.*.email' => ['nullable', 'string'],
            'guests.*.address' => ['nullable', 'string'],
            'guests.*.note' => ['nullable', 'string'],
            'guests.*.group_name' => ['nullable', 'string'],
            'guests.*.is_vip' => ['nullable', 'boolean'],
        ]);

        $result = $this->guestService->importConfirm($wedding, $validated['guests']);

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
        $date = $wedding->wedding_date?->format('Y-m-d');

        // Wedding name + date so a folder of downloads stays readable; the
        // wedding code stands in when the name is empty.
        $filename = implode('-', array_filter([
            'guests',
            $safeName !== '' ? $safeName : $wedding->wedding_code,
            $date,
        ])).'.xlsx';

        // Khmer wedding names survive only in the RFC 5987 `filename*=` form,
        // so hand the header to Symfony with an ASCII fallback for old clients.
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename,
            implode('-', array_filter(['guests', $wedding->wedding_code, $date])).'.xlsx',
        );

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => $disposition,
        ]);
    }

    public function bulkInvite(BulkInviteRequest $request, Wedding $wedding): JsonResponse
    {
        $rawIds = $request->input('guest_ids');
        $guestIds = is_array($rawIds) && count($rawIds) > 0
            ? array_values(array_map('intval', array_filter($rawIds, 'is_numeric')))
            : null;

        $updated = $this->guestService->bulkInvite(
            $wedding,
            $guestIds,
            (int) $request->validated('invitation_id'),
        );

        return response()->json(['message' => "Invitation attached to {$updated} guests."]);
    }

    public function bulkGroup(BulkGroupRequest $request, Wedding $wedding): JsonResponse
    {
        $rawIds = $request->input('guest_ids');
        $guestIds = is_array($rawIds) && count($rawIds) > 0
            ? array_values(array_map('intval', array_filter($rawIds, 'is_numeric')))
            : null;

        $updated = $this->guestService->bulkGroup(
            $wedding,
            $guestIds,
            (int) $request->validated('guest_group_id'),
        );

        return response()->json(['message' => "Group assigned to {$updated} guests."]);
    }

    /**
     * Render a guest's personal check-in QR code as an SVG (for printing onto
     * the invitation).
     */
    public function qrCode(Wedding $wedding, Guest $guest): Response
    {
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
        return GuestResource::make(
            $this->guestService->setCheckIn($guest, $request->boolean('arrived', true)),
        );
    }

    public function checkInStats(Wedding $wedding): JsonResponse
    {
        return response()->json(['data' => $this->guestService->checkInStats($wedding)]);
    }
}
