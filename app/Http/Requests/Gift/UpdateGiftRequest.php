<?php

namespace App\Http\Requests\Gift;

use App\Enums\GiftType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $weddingId = $this->route('wedding')?->id;

        return [
            'guest_id' => [
                'sometimes',
                'nullable',
                Rule::exists('guests', 'id')->where('wedding_id', $weddingId),
            ],
            'gift_type' => ['sometimes', Rule::enum(GiftType::class)],
            'amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'in:USD,KHR'],
            'item_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'received_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
