<?php

namespace App\Http\Controllers;

use App\Http\Requests\Guest\StoreGuestGroupRequest;
use App\Http\Requests\Guest\UpdateGuestGroupRequest;
use App\Http\Resources\GuestGroupResource;
use App\Models\GuestGroup;
use App\Models\Wedding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GuestGroupController extends Controller
{
    public function index(Wedding $wedding): AnonymousResourceCollection
    {
        return GuestGroupResource::collection(
            $wedding->guestGroups()->withCount('guests')->orderBy('sort_order')->orderBy('name')->get(),
        );
    }

    public function store(StoreGuestGroupRequest $request, Wedding $wedding): JsonResponse
    {
        $group = $wedding->guestGroups()->create($request->validated());

        return GuestGroupResource::make($group)->response()->setStatusCode(201);
    }

    public function update(UpdateGuestGroupRequest $request, Wedding $wedding, GuestGroup $guestGroup): GuestGroupResource
    {
        $guestGroup->update($request->validated());

        return GuestGroupResource::make($guestGroup->loadCount('guests'));
    }

    public function destroy(Wedding $wedding, GuestGroup $guestGroup): JsonResponse
    {
        $guestGroup->delete();

        return response()->json(['message' => 'Guest group deleted.']);
    }
}
