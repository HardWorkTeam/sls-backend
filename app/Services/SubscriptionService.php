<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($wedding, $package) {
            // Lock the wedding's latest subscription row so a concurrent
            // select/pay/confirm on the same wedding serializes instead of
            // last-write-wins clobbering each other.
            $current = $wedding->subscriptions()
                ->with('package')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            // A genuinely PAID plan locks in. A free plan auto-"pays" too, but the
            // couple must stay able to upgrade away from it, so a paid *free* plan
            // is not a lock.
            if ($current
                && $current->status === SubscriptionStatus::Paid
                && ! ($current->package?->isFree() ?? false)) {
                return $current;
            }

            // Re-selecting the plan they already hold is a no-op.
            if ($current
                && $current->status === SubscriptionStatus::Paid
                && $current->package_id === $package->id) {
                return $current;
            }

            // A payment under admin review must be decided before the plan can
            // change: mutating the row here would wipe the submitted payment
            // reference and re-point the money at a different package while the
            // admin may be confirming it.
            abort_if(
                $current?->status === SubscriptionStatus::Submitted,
                422,
                'Your payment is awaiting confirmation. It must be reviewed before you can change plans.',
            );

            // Free plans need no payment — activate (paid) on selection. Paid plans
            // start pending and await the couple's payment + admin confirmation.
            $isFree = $package->isFree();

            // Only a still-unpaid draft (pending) is reused. A paid free plan
            // stays as its own row so the couple keeps their Free access while
            // the upgrade awaits payment; rejected rows are kept as the admin's
            // audit trail of the decision.
            $subscription = $current?->status === SubscriptionStatus::Pending
                ? $current
                : new Subscription;
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
        });
    }

    /**
     * Record a couple's manual payment claim against the current subscription.
     */
    public function submitPayment(Wedding $wedding, array $data): Subscription
    {
        return DB::transaction(function () use ($wedding, $data) {
            $subscription = $wedding->subscriptions()
                ->latest('id')
                ->lockForUpdate()
                ->first();

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
        });
    }

    /**
     * Admin decision on a submitted payment: confirm (paid) or reject. Only a
     * SUBMITTED payment can be confirmed; only a submitted or (mis-clicked)
     * paid one can be rejected — so a stale admin screen can never activate a
     * plan the couple has since swapped, or one nobody claimed to pay for.
     */
    public function confirm(Subscription $subscription, bool $paid): Subscription
    {
        return DB::transaction(function () use ($subscription, $paid) {
            /** @var Subscription $subscription */
            $subscription = Subscription::query()
                ->whereKey($subscription->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($paid) {
                abort_unless(
                    $subscription->status === SubscriptionStatus::Submitted,
                    422,
                    "Only a submitted payment can be confirmed (this one is {$subscription->status->value}). Refresh and try again.",
                );

                $subscription->fill(['status' => SubscriptionStatus::Paid->value, 'paid_at' => now()]);
            } else {
                abort_unless(
                    in_array($subscription->status, [SubscriptionStatus::Submitted, SubscriptionStatus::Paid], true),
                    422,
                    "Only a submitted or paid subscription can be rejected (this one is {$subscription->status->value}).",
                );

                $subscription->fill(['status' => SubscriptionStatus::Rejected->value, 'paid_at' => null]);
            }

            $subscription->save();

            return $subscription->load(['package', 'wedding.createdBy']);
        });
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
