<?php

namespace App\Http\Requests\Gift;

use App\Enums\GiftType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGiftRequest extends FormRequest
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
                'nullable',
                Rule::exists('guests', 'id')->where('wedding_id', $weddingId),
            ],
            'gift_type' => ['required', Rule::enum(GiftType::class)],
            'amount' => ['nullable', 'numeric', 'min:0', 'required_unless:gift_type,item'],
            'currency' => ['required', 'string', 'in:USD,KHR'],
            'item_name' => ['nullable', 'string', 'max:255', 'required_if:gift_type,item'],
            'note' => ['nullable', 'string', 'max:2000'],
            'received_at' => ['nullable', 'date'],
        ];
    }
}
