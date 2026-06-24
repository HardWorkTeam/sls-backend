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
     * The wedding's current subscription plus the platform's payment details
     * (KHQR / bank) the couple needs in order to pay.
     */
    public function show(Wedding $wedding): JsonResponse
    {
        $current = $this->subscriptions->current($wedding);

        return response()->json([
            'data' => [
                'subscription' => $current ? SubscriptionResource::make($current) : null,
                'payment_details' => config('services.platform_payment'),
            ],
        ]);
    }

    public function select(SelectPackageRequest $request, Wedding $wedding): SubscriptionResource
    {
        $package = Package::query()->findOrFail($request->validated('package_id'));

        return SubscriptionResource::make($this->subscriptions->selectPackage($wedding, $package));
    }

    public function pay(SubmitPaymentRequest $request, Wedding $wedding): SubscriptionResource
    {
        return SubscriptionResource::make(
            $this->subscriptions->submitPayment($wedding, $request->validated()),
        );
    }
}
