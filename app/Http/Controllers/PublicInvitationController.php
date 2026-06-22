<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rsvp\PublicRsvpRequest;
use App\Http\Resources\PublicInvitationResource;
use App\Services\InvitationService;
use App\Services\RsvpService;
use Illuminate\Http\JsonResponse;

class PublicInvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitationService,
        private readonly RsvpService $rsvpService,
    ) {}

    public function show(string $code): PublicInvitationResource
    {
        $invitation = $this->invitationService->findByCode($code);

        abort_unless((bool) $invitation, 404, 'Invitation not found.');

        return PublicInvitationResource::make($invitation);
    }

    public function rsvp(PublicRsvpRequest $request, string $code): JsonResponse
    {
        $invitation = $this->invitationService->findPublishedByCode($code);

        abort_unless((bool) $invitation, 404, 'Invitation not found.');

        $response = $this->rsvpService->submitPublic($invitation, $request->validated());

        return response()->json([
            'message' => 'Thank you! Your RSVP has been recorded.',
            'data' => [
                'status' => $response->status,
                'guest_name' => $response->guest_name,
                'number_of_guests' => $response->number_of_guests,
            ],
        ], 201);
    }
}
