<?php

namespace App\Http\Resources;

use App\Enums\SubscriptionStatus;
use App\Support\PlanCapabilities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeddingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_code' => $this->wedding_code,
            'wedding_name' => $this->wedding_name,
            'bride_name' => $this->bride_name,
            'groom_name' => $this->groom_name,
            'bride_photo_path' => $this->bride_photo_path,
            'groom_photo_path' => $this->groom_photo_path,
            'phone' => $this->phone,
            'email' => $this->email,
            'wedding_date' => $this->wedding_date?->toDateString(),
            'wedding_time' => $this->wedding_time,
            'ceremony_venue' => $this->ceremony_venue,
            'reception_venue' => $this->reception_venue,
            'google_map_link' => $this->google_map_link,
            'story_description' => $this->story_description,
            'status' => $this->status,
            'published_at' => $this->published_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'package' => PackageResource::make($this->whenLoaded('package')),
            'payment_status' => $this->whenLoaded(
                'subscriptions',
                fn () => $this->subscriptions->sortByDesc('id')->first()?->status?->value ?? 'unpaid',
            ),
            // Whether the wedding holds ANY paid (active) plan. `payment_status`
            // above tracks the latest subscription — which is the in-flight
            // upgrade while one is pending — so gating must not key off it.
            'has_active_plan' => $this->whenLoaded(
                'subscriptions',
                fn () => $this->subscriptions->contains(
                    fn ($subscription) => $subscription->status === SubscriptionStatus::Paid,
                ),
            ),
            // What this wedding can do — gated on its PAID subscription only.
            // Computed from the eager-loaded `subscriptions` + `package`
            // relations (index + show both load them) to avoid an N+1.
            'capabilities' => $this->when(
                $this->resource->relationLoaded('subscriptions'),
                function () {
                    $paid = $this->subscriptions
                        ->sortByDesc('id')
                        ->firstWhere('status', SubscriptionStatus::Paid);

                    // The wedding's `package` relation mirrors the latest
                    // SELECTION, which diverges from the paid plan while an
                    // upgrade awaits payment/confirmation — capabilities must
                    // come from the paid subscription's own package. The mirror
                    // is only trusted when it matches (the common, paid-locked
                    // case); otherwise fall back to the subscription's package
                    // (lazy load, only during an upgrade window).
                    $package = null;

                    if ($paid) {
                        $mirrorMatches = $this->resource->relationLoaded('package')
                            && $this->package?->id === $paid->package_id;
                        $package = $mirrorMatches ? $this->package : $paid->package;
                    }

                    return PlanCapabilities::forPaidPackage($package, $paid !== null)->toArray();
                },
            ),
            'created_by' => UserResource::make($this->whenLoaded('createdBy')),
            'members' => WeddingMemberResource::collection($this->whenLoaded('members')),
            'guests_count' => $this->whenCounted('guests'),
            'invitations_count' => $this->whenCounted('invitations'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
