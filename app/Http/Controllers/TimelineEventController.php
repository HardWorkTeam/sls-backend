<?php

namespace App\Http\Controllers;

use App\Http\Requests\Timeline\StoreTimelineEventRequest;
use App\Http\Requests\Timeline\UpdateTimelineEventRequest;
use App\Http\Resources\TimelineEventResource;
use App\Models\TimelineEvent;
use App\Models\Wedding;
use App\Repositories\TimelineRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TimelineEventController extends Controller
{
    public function __construct(private readonly TimelineRepository $timeline) {}

    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        return TimelineEventResource::collection(
            $this->timeline->forWedding($wedding, $request->query('category')),
        );
    }

    public function store(StoreTimelineEventRequest $request, Wedding $wedding): JsonResponse
    {
        $event = $wedding->timelineEvents()->create($request->validated());

        return TimelineEventResource::make($event)->response()->setStatusCode(201);
    }

    public function update(UpdateTimelineEventRequest $request, Wedding $wedding, TimelineEvent $event): TimelineEventResource
    {
        abort_unless($event->wedding_id === $wedding->id, 404);

        $event->update($request->validated());

        return TimelineEventResource::make($event);
    }

    public function destroy(Wedding $wedding, TimelineEvent $event): JsonResponse
    {
        abort_unless($event->wedding_id === $wedding->id, 404);

        $event->delete();

        return response()->json(['message' => 'Timeline event deleted.']);
    }
}
