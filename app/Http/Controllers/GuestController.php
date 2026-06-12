<?php

namespace App\Http\Controllers;

use App\Http\Requests\Guest\BulkInviteRequest;
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
        $csv = $this->guestService->exportCsv($wedding, [
            'search' => $request->query('search'),
            'guest_group_id' => $request->query('guest_group_id'),
        ]);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"guests-{$wedding->wedding_code}.csv\"",
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
}
