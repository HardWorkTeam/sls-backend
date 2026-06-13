<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'item_name' => $this->item_name,
            'vendor' => $this->vendor,
            'amount' => (float) $this->amount,
            'paid_amount' => (float) $this->paid_amount,
            'status' => $this->status,
            'note' => $this->note,
            'spent_at' => $this->spent_at?->toDateString(),
        ];
    }
}
