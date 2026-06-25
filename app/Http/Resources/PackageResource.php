<?php

namespace App\Http\Resources;

use App\Support\PlanCapabilities;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price !== null ? (float) $this->price : null,
            'currency' => $this->currency,
            'features' => $this->features,
            // Always the resolved, structured capabilities (derived from the
            // feature strings for packages with no explicit definition yet)
            // so the admin form can edit them directly.
            'capabilities' => PlanCapabilities::fromPackage($this->resource)->toArray(),
            'is_active' => $this->is_active,
        ];
    }
}
