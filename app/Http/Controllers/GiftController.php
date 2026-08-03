<?php

namespace App\Http\Controllers;

use App\Http\Requests\Gift\StoreGiftRequest;
use App\Http\Requests\Gift\UpdateGiftRequest;
use App\Http\Resources\GiftResource;
use App\Models\Gift;
use App\Models\Wedding;
use App\Repositories\GiftRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GiftController extends Controller
{
    public function __construct(private readonly GiftRepository $gifts) {}

    public function index(Request $request, Wedding $wedding): AnonymousResourceCollection
    {
        return GiftResource::collection(
            $this->gifts->searchForWedding(
                $wedding,
                $request->query('gift_type'),
                (int) $request->query('per_page', '15'),
                $request->query('search'),
            ),
        );
    }

    public function store(StoreGiftRequest $request, Wedding $wedding): JsonResponse
    {
        $gift = $wedding->gifts()->create($request->validated() + ['received_at' => $request->validated('received_at') ?? now()]);

        return GiftResource::make($gift->load('guest'))->response()->setStatusCode(201);
    }

    public function update(UpdateGiftRequest $request, Wedding $wedding, Gift $gift): GiftResource
    {
        $gift->update($request->validated());

        return GiftResource::make($gift->load('guest'));
    }

    public function destroy(Wedding $wedding, Gift $gift): JsonResponse
    {
        $gift->delete();

        return response()->json(['message' => 'Gift deleted.']);
    }

    public function summary(Wedding $wedding): JsonResponse
    {
        return response()->json(['data' => $this->gifts->summaryForWedding($wedding)]);
    }
}
