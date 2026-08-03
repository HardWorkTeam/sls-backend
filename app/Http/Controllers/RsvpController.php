<?php

namespace App\Http\Controllers;

use App\Http\Requests\Rsvp\UpdateRsvpRequest;
use App\Http\Resources\RsvpResponseResource;
use App\Models\RsvpResponse;
use App\Models\Wedding;
use App\Services\RsvpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RsvpController extends Controller
{
    public function __construct(private readonly RsvpService $rsvpService) {}

    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        $responses = $this->rsvpService->list(
            $wedding,
            $request->query('status'),
            $request->query('search'),
            (int) $request->query('per_page', '15'),
        );

        return RsvpResponseResource::collection($responses);
    }

    public function stats(Wedding $wedding): JsonResponse
    {
        return response()->json(['data' => $this->rsvpService->stats($wedding)]);
    }

    public function update(UpdateRsvpRequest $request, Wedding $wedding, RsvpResponse $rsvpResponse): RsvpResponseResource
    {
        return RsvpResponseResource::make($this->rsvpService->update($rsvpResponse, $request->validated()));
    }

    public function destroy(Wedding $wedding, RsvpResponse $rsvpResponse): JsonResponse
    {
        $this->rsvpService->delete($rsvpResponse);

        return response()->json(['message' => 'RSVP response deleted.']);
    }
}
