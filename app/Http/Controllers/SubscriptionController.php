<?php

namespace App\Http\Controllers;

use App\Http\Requests\Subscription\SelectPackageRequest;
use App\Http\Requests\Subscription\SubmitPaymentRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Package;
use App\Models\Wedding;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * Current subscription for the wedding + the platform's payment details.
     */
    public function show(Wedding $wedding): JsonResponse
    {
        $subscription = $this->subscriptions->current($wedding);

        return response()->json([
            'data' => $subscription ? SubscriptionResource::make($subscription) : null,
            'payment_details' => config('services.platform_payment'),
        ]);
    }

    public function select(SelectPackageRequest $request, Wedding $wedding): JsonResponse
    {
        $package = Package::query()->findOrFail($request->validated('package_id'));

        $subscription = $this->subscriptions->selectPackage($wedding, $package);

        return SubscriptionResource::make($subscription)->response()->setStatusCode(201);
    }

    public function pay(SubmitPaymentRequest $request, Wedding $wedding): JsonResponse
    {
        $subscription = $this->subscriptions->current($wedding);

        abort_unless((bool) $subscription, 404, 'Select a package first.');

        return SubscriptionResource::make(
            $this->subscriptions->submitPayment($subscription, $request->validated()),
        )->response();
    }
}
