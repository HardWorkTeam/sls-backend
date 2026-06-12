<?php

namespace App\Http\Controllers;

use App\Http\Requests\Wedding\StoreWeddingMemberRequest;
use App\Http\Resources\WeddingMemberResource;
use App\Models\Wedding;
use App\Models\WeddingMember;
use App\Services\WeddingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WeddingMemberController extends Controller
{
    public function __construct(private readonly WeddingService $weddingService) {}

    public function index(Wedding $wedding): AnonymousResourceCollection
    {
        return WeddingMemberResource::collection(
            $wedding->members()->with('user.roles')->get(),
        );
    }

    public function store(StoreWeddingMemberRequest $request, Wedding $wedding): JsonResponse
    {
        $member = $this->weddingService->addMember($wedding, $request->validated());

        return WeddingMemberResource::make($member)->response()->setStatusCode(201);
    }

    public function destroy(Wedding $wedding, WeddingMember $member): JsonResponse
    {
        $this->weddingService->removeMember($wedding, $member);

        return response()->json(['message' => 'Member removed.']);
    }
}
