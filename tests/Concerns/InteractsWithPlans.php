<?php

namespace Tests\Concerns;

use App\Enums\SubscriptionStatus;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Wedding;

trait InteractsWithPlans
{
    /** All plan-gated module keys. */
    private const ALL_MODULES = ['seating', 'gallery', 'gifts', 'expense', 'rsvp', 'timeline'];

    /**
     * Create a wedding owned by $owner with a PAID subscription whose package
     * capabilities enable the given modules and limits. Only a paid plan
     * unlocks gated modules and a non-zero guest / design allowance.
     *
     * @param  array<int, string>  $modules  module keys to enable (default: all)
     */
    protected function paidWeddingFor(
        User $owner,
        array $modules = self::ALL_MODULES,
        ?int $guestLimit = null,
        ?int $invitationDesignLimit = null,
    ): Wedding {
        $package = Package::query()->create([
            'name' => 'Test Plan',
            'price' => 100,
            'currency' => 'USD',
            'features' => ['All modules'],
            'capabilities' => [
                'modules' => collect(self::ALL_MODULES)
                    ->mapWithKeys(fn (string $m) => [$m => in_array($m, $modules, true)])
                    ->all(),
                'guest_limit' => $guestLimit,
                'invitation_design_limit' => $invitationDesignLimit,
            ],
            'is_active' => true,
        ]);

        $wedding = Wedding::factory()->create(['created_by_user_id' => $owner->id]);

        Subscription::query()->create([
            'wedding_id' => $wedding->id,
            'package_id' => $package->id,
            'amount' => 100,
            'currency' => 'USD',
            'status' => SubscriptionStatus::Paid->value,
            'paid_at' => now(),
        ]);

        return $wedding;
    }
}
