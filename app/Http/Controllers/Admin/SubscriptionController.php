<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $status = $request->query('status');

        return SubscriptionResource::collection(
            Subscription::query()
                ->with(['package', 'wedding'])
                ->when($status, fn (Builder $query) => $query->where('status', $status))
                ->latest('id')
                ->paginate((int) $request->query('per_page', '15')),
        );
    }

    public function confirm(Request $request, Subscription $subscription): SubscriptionResource
    {
        $paid = $request->boolean('paid', true);

        return SubscriptionResource::make($this->subscriptions->confirm($subscription, $paid));
    }
}
