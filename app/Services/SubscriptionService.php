<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Owns the package-payment lifecycle: a couple selects a package (pending),
 * submits a manual KHQR/bank payment reference (submitted), and a super admin
 * confirms it (paid) or rejects it. Each wedding has at most one active
 * subscription; a paid one locks the plan.
 */
class SubscriptionService
{
    /**
     * The wedding's most recent subscription (with its package), or null.
     */
    public function current(Wedding $wedding): ?Subscription
    {
        return $wedding->subscriptions()
            ->with('package')
            ->latest('id')
            ->first();
    }

    /**
     * Select (or change) the package for a wedding. Upserts the wedding's
     * current non-paid subscription and mirrors the choice onto the wedding.
     * A paid subscription locks the plan and is returned unchanged.
     */
    public function selectPackage(Wedding $wedding, Package $package): Subscription
    {
        $current = $this->current($wedding);

        // A genuinely PAID plan locks in. A free plan auto-"pays" too, but the
        // couple must stay able to upgrade away from it, so a paid *free* plan
        // is not a lock.
        if ($current
            && $current->status === SubscriptionStatus::Paid
            && ! ($current->package?->isFree() ?? false)) {
            return $current->load('package');
        }

        // Free plans need no payment — activate (paid) on selection. Paid plans
        // start pending and await the couple's payment + admin confirmation.
        $isFree = $package->isFree();

        $subscription = $current ?? new Subscription(['wedding_id' => $wedding->id]);
        $subscription->fill([
            'wedding_id' => $wedding->id,
            'package_id' => $package->id,
            'amount' => $package->price ?? 0,
            'currency' => $package->currency ?? 'USD',
            'status' => $isFree ? SubscriptionStatus::Paid->value : SubscriptionStatus::Pending->value,
            'payment_method' => null,
            'payment_reference' => null,
            'submitted_at' => null,
            'paid_at' => $isFree ? now() : null,
        ]);
        $subscription->save();

        $wedding->forceFill(['package_id' => $package->id])->save();

        return $subscription->load('package');
    }

    /**
     * Record a couple's manual payment claim against the current subscription.
     */
    public function submitPayment(Wedding $wedding, array $data): Subscription
    {
        $subscription = $this->current($wedding);

        abort_if($subscription === null, 422, 'Select a package before submitting payment.');
        abort_if($subscription->status === SubscriptionStatus::Paid, 422, 'This plan is already paid.');

        $subscription->fill([
            'status' => SubscriptionStatus::Submitted->value,
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'],
            'submitted_at' => now(),
        ]);
        $subscription->save();

        return $subscription->load('package');
    }

    /**
     * Admin decision on a submitted payment: confirm (paid) or reject.
     */
    public function confirm(Subscription $subscription, bool $paid): Subscription
    {
        $subscription->fill($paid
            ? ['status' => SubscriptionStatus::Paid->value, 'paid_at' => now()]
            : ['status' => SubscriptionStatus::Rejected->value, 'paid_at' => null]);
        $subscription->save();

        return $subscription->load(['package', 'wedding.createdBy']);
    }

    /**
     * Paginated subscriptions for the admin payments screen, newest first.
     */
    public function listForAdmin(?string $status, int $perPage): LengthAwarePaginator
    {
        return Subscription::query()
            ->with(['package', 'wedding.createdBy'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Counts grouped by status for the admin payments summary cards.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        $counts = Subscription::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(SubscriptionStatus::values())
            ->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)])
            ->all();
    }
}
