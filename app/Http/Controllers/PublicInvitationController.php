<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rsvp\PublicRsvpRequest;
use App\Http\Resources\PublicInvitationResource;
use App\Services\InvitationService;
use App\Services\RsvpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PublicInvitationController extends Controller
{
    /**
     * Seconds a serialized public invitation stays cached. Bounded staleness
     * that matches the RSVP frontend's revalidate window; writes also forget
     * the key (see InvitationService) so edits usually appear immediately.
     */
    private const CACHE_TTL = 30;

    public function __construct(
        private readonly InvitationService $invitationService,
        private readonly RsvpService $rsvpService,
    ) {}

    public function show(string $code): JsonResponse
    {
        // Support viewing draft/unpublished invitations if a valid preview token is provided.
        // This is used by the invitation editor's live preview frame.
        $previewToken = request()->query('preview_token');
        if ($previewToken && is_string($previewToken) && hash_equals(hash_hmac('sha256', $code, (string) config('app.key')), $previewToken)) {
            $invitation = $this->invitationService->findByCode($code);
            abort_unless((bool) $invitation, 404, 'Invitation not found.');
            $data = PublicInvitationResource::make($invitation)->resolve();
            return response()->json(['data' => $data]);
        }

        // Cache the serialized payload, not the Eloquent model: every guest hit
        // otherwise re-runs a 4-table join (wedding + timeline + albums + media)
        // against Postgres. Unknown codes are intentionally NOT cached so random
        // codes can't fill the cache; they 404 straight from the DB.
        $key = InvitationService::publicCacheKey($code);
        $data = Cache::get($key);

        if ($data === null) {
            // Public site only ever serves PUBLISHED invitations — drafts must
            // not be viewable by guessing/knowing the code (consistent with the
            // rsvp() endpoint, which already requires a published invitation).
            $invitation = $this->invitationService->findPublishedByCode($code);

            abort_unless((bool) $invitation, 404, 'Invitation not found.');

            // Encode through JSON so nested Resource objects (InvitationTemplateResource,
            // TimelineEventResource, AlbumResource) are fully resolved to plain
            // scalars/arrays before being stored. Storing un-resolved Resource instances
            // causes __PHP_Incomplete_Class errors on retrieval.
            $resolved = PublicInvitationResource::make($invitation)->resolve();
            $data = json_decode(json_encode($resolved), true);
            Cache::put($key, $data, self::CACHE_TTL);
        }

        return response()->json(['data' => $data]);
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
