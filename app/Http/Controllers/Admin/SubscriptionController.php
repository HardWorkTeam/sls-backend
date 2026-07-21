<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\ConfirmSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $paginator = $this->subscriptions->listForAdmin(
            $request->query('status'),
            $request->query('search'),
            (int) $request->query('per_page', '15'),
        );

        return SubscriptionResource::collection($paginator)
            ->additional(['summary' => ['status_counts' => $this->subscriptions->statusCounts()]]);
    }

    public function confirm(ConfirmSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        return response()->json([
            'data' => SubscriptionResource::make(
                $this->subscriptions->confirm($subscription, $request->boolean('paid')),
            ),
        ]);
    }
}
