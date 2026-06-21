<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Wedding;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    /**
     * The current (latest) subscription for a wedding, if any.
     */
    public function current(Wedding $wedding): ?Subscription
    {
        return $wedding->subscriptions()->with('package')->latest('id')->first();
    }

    /**
     * Couple selects a package for their wedding. Creates a fresh pending
     * subscription (snapshotting price/currency) and links the package to the
     * wedding. Replaces any previous unpaid selection.
     */
    public function selectPackage(Wedding $wedding, Package $package): Subscription
    {
        if (! $package->is_active) {
            throw ValidationException::withMessages([
                'package_id' => ['This package is not available.'],
            ]);
        }

        // Drop any earlier not-yet-paid selection so there is one active choice.
        $wedding->subscriptions()
            ->whereIn('status', [SubscriptionStatus::Pending->value, SubscriptionStatus::Submitted->value])
            ->delete();

        $wedding->forceFill(['package_id' => $package->id])->save();

        return $wedding->subscriptions()->create([
            'package_id' => $package->id,
            'amount' => $package->price ?? 0,
            'currency' => $package->currency ?? 'USD',
            'status' => SubscriptionStatus::Pending->value,
        ])->load('package');
    }

    /**
     * Couple submits proof that they paid (manual KHQR / bank transfer).
     * Moves the subscription to "submitted" for admin confirmation.
     *
     * @param  array{payment_method: string, payment_reference?: string|null}  $attributes
     */
    public function submitPayment(Subscription $subscription, array $attributes): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Paid->value) {
            throw ValidationException::withMessages([
                'status' => ['This package is already paid.'],
            ]);
        }

        $subscription->update([
            'payment_method' => $attributes['payment_method'],
            'payment_reference' => $attributes['payment_reference'] ?? null,
            'status' => SubscriptionStatus::Submitted->value,
            'submitted_at' => now(),
        ]);

        return $subscription->load('package');
    }

    /**
     * Admin confirms (or rejects) a submitted payment.
     */
    public function confirm(Subscription $subscription, bool $paid): Subscription
    {
        $subscription->update($paid
            ? ['status' => SubscriptionStatus::Paid->value, 'paid_at' => now()]
            : ['status' => SubscriptionStatus::Pending->value, 'submitted_at' => null]);

        return $subscription->load(['package', 'wedding']);
    }
}
