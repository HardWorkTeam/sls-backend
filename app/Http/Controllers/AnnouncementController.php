<?php

namespace App\Http\Controllers;

use App\Http\Requests\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Announcement\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Wedding;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    public function __construct(private readonly AnnouncementService $announcementService) {}

    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        return AnnouncementResource::collection(
            $this->announcementService->list($wedding, (int) $request->query('per_page', '15')),
        );
    }

    public function store(StoreAnnouncementRequest $request, Wedding $wedding): JsonResponse
    {
        $announcement = $this->announcementService->create($wedding, $request->user(), $request->validated());

        return AnnouncementResource::make($announcement)->response()->setStatusCode(201);
    }

    public function update(UpdateAnnouncementRequest $request, Wedding $wedding, Announcement $announcement): AnnouncementResource
    {
        abort_unless($announcement->wedding_id === $wedding->id, 404);

        return AnnouncementResource::make(
            $this->announcementService->update($announcement, $request->validated()),
        );
    }

    public function destroy(Wedding $wedding, Announcement $announcement): JsonResponse
    {
        abort_unless($announcement->wedding_id === $wedding->id, 404);

        $this->announcementService->delete($announcement);

        return response()->json(['message' => 'Announcement deleted.']);
    }

    public function send(Wedding $wedding, Announcement $announcement): AnnouncementResource
    {
        abort_unless($announcement->wedding_id === $wedding->id, 404);

        return AnnouncementResource::make($this->announcementService->send($announcement));
    }
}
