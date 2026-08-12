<?php

namespace App\Http\Controllers;

use App\Http\Requests\Wedding\InviteWeddingMemberRequest;
use App\Http\Requests\Wedding\StoreWeddingMemberRequest;
use App\Http\Requests\Wedding\UpdateWeddingMemberRequest;
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
        $members = $wedding->members()->with('user.roles')->get();

        // Ensure the wedding owner is always displayed in the members list
        if (! $members->contains('user_id', $wedding->created_by_user_id)) {
            $owner = $wedding->createdBy()->with('roles')->first();
            
            if ($owner) {
                $ownerMember = new WeddingMember([
                    'wedding_id' => $wedding->id,
                    'user_id' => $owner->id,
                    'member_role' => \App\Enums\MemberRole::Member->value,
                    'is_primary' => true,
                ]);
                
                $ownerMember->id = 0; // Temporary ID for the frontend
                $ownerMember->setRelation('user', $owner);
                
                $members->prepend($ownerMember);
            }
        }

        return WeddingMemberResource::collection($members);
    }

    public function store(StoreWeddingMemberRequest $request, Wedding $wedding): JsonResponse
    {
        $member = $this->weddingService->addMember($wedding, $request->validated());

        return WeddingMemberResource::make($member)->response()->setStatusCode(201);
    }

    public function invite(InviteWeddingMemberRequest $request, Wedding $wedding): JsonResponse
    {
        $result = $this->weddingService->inviteMember($wedding, $request->validated());

        return response()->json([
            'data' => WeddingMemberResource::make($result['member']->load('user.roles')),
            'temp_password' => $result['temp_password'],
        ], 201);
    }

    public function update(UpdateWeddingMemberRequest $request, Wedding $wedding, WeddingMember $member): JsonResponse
    {
        $updatedMember = $this->weddingService->updateMember($wedding, $member, $request->validated());

        return WeddingMemberResource::make($updatedMember)->response();
    }

    public function destroy(Wedding $wedding, WeddingMember $member): JsonResponse
    {
        $this->weddingService->removeMember($member);

        return response()->json(['message' => 'Member removed.']);
    }
}
