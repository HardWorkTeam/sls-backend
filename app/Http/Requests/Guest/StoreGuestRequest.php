<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'is_vip' => ['sometimes', 'boolean'],
            'guest_group_id' => [
                'nullable',
                Rule::exists('guest_groups', 'id')->where('wedding_id', $weddingId),
            ],
            'invitation_id' => [
                'nullable',
                Rule::exists('invitations', 'id')->where('wedding_id', $weddingId),
            ],
        ];
    }
}
