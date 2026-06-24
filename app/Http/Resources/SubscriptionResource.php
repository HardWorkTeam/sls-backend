<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'package_id' => $this->package_id,
            'package' => PackageResource::make($this->whenLoaded('package')),
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            // Flattened wedding context for the admin payments list.
            'wedding_code' => $this->whenLoaded('wedding', fn () => $this->wedding->wedding_code),
            'wedding_name' => $this->whenLoaded('wedding', fn () => $this->wedding->wedding_name),
            'couple' => $this->whenLoaded('wedding', fn () => trim("{$this->wedding->bride_name} & {$this->wedding->groom_name}")),
            'user_name' => $this->whenLoaded('wedding', fn () => $this->wedding->createdBy?->name),
        ];
    }
}
