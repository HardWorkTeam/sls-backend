<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invitation\StoreInvitationRequest;
use App\Http\Requests\Invitation\UpdateInvitationRequest;
use App\Http\Resources\InvitationResource;
use App\Models\Invitation;
use App\Models\Wedding;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitationService) {}

    public function index(Wedding $wedding): AnonymousResourceCollection
    {
        return InvitationResource::collection($this->invitationService->listForWedding($wedding));
    }

    public function store(StoreInvitationRequest $request, Wedding $wedding): JsonResponse
    {
        $invitation = $this->invitationService->create($wedding, $request->validated());

        return InvitationResource::make($invitation)->response()->setStatusCode(201);
    }

    public function show(Wedding $wedding, Invitation $invitation): InvitationResource
    {
        return InvitationResource::make(
            $invitation->load('template')->loadCount(['guests', 'rsvpResponses']),
        );
    }

    public function update(UpdateInvitationRequest $request, Wedding $wedding, Invitation $invitation): InvitationResource
    {
        return InvitationResource::make(
            $this->invitationService->update($invitation, $request->validated()),
        );
    }

    public function destroy(Wedding $wedding, Invitation $invitation): JsonResponse
    {
        $this->invitationService->delete($invitation);

        return response()->json(['message' => 'Invitation deleted.']);
    }

    public function publish(Wedding $wedding, Invitation $invitation): InvitationResource
    {
        return InvitationResource::make($this->invitationService->publish($invitation));
    }

    public function unpublish(Wedding $wedding, Invitation $invitation): InvitationResource
    {
        return InvitationResource::make($this->invitationService->unpublish($invitation));
    }

    public function qrCode(Wedding $wedding, Invitation $invitation): Response
    {
        return response($this->invitationService->qrCodeSvg($invitation), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => "inline; filename=\"invitation-{$invitation->invitation_code}.svg\"",
        ]);
    }
}
