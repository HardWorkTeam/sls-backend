<?php

namespace App\Http\Requests\Guest;

use App\Enums\GuestGroupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuestGroupRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(GuestGroupType::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
