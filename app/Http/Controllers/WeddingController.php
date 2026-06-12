<?php

namespace App\Http\Controllers;

use App\Enums\WeddingStatus;
use App\Http\Requests\Wedding\ChangeWeddingStatusRequest;
use App\Http\Requests\Wedding\StoreWeddingRequest;
use App\Http\Requests\Wedding\UpdateWeddingRequest;
use App\Http\Resources\WeddingResource;
use App\Models\Wedding;
use App\Services\WeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WeddingController extends Controller
{
    public function __construct(private readonly WeddingService $weddingService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $weddings = $this->weddingService->list(
            $request->user(),
            $request->query('search'),
            $request->query('status'),
            (int) $request->query('per_page', '15'),
        );

        return WeddingResource::collection($weddings);
    }

    public function store(StoreWeddingRequest $request): JsonResponse
    {
        $wedding = $this->weddingService->create($request->user(), $request->validated());

        return WeddingResource::make($wedding)->response()->setStatusCode(201);
    }

    public function show(Wedding $wedding): WeddingResource
    {
        return WeddingResource::make(
            $wedding->load(['package', 'createdBy', 'members.user'])->loadCount(['guests', 'invitations']),
        );
    }

    public function update(UpdateWeddingRequest $request, Wedding $wedding): WeddingResource
    {
        return WeddingResource::make(
            $this->weddingService->update($wedding, $request->validated()),
        );
    }

    public function destroy(Wedding $wedding): JsonResponse
    {
        $this->weddingService->delete($wedding);

        return response()->json(['message' => 'Wedding deleted.']);
    }

    public function changeStatus(ChangeWeddingStatusRequest $request, Wedding $wedding): WeddingResource
    {
        return WeddingResource::make(
            $this->weddingService->changeStatus($wedding, WeddingStatus::from($request->validated('status'))),
        );
    }

    public function dashboard(Wedding $wedding): JsonResponse
    {
        return response()->json(['data' => $this->weddingService->dashboard($wedding)]);
    }
}
