<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuestRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'note' => ['sometimes', 'nullable', 'string'],
            'is_vip' => ['sometimes', 'boolean'],
            'guest_group_id' => [
                'sometimes',
                'nullable',
                Rule::exists('guest_groups', 'id')->where('wedding_id', $weddingId),
            ],
            'invitation_id' => [
                'sometimes',
                'nullable',
                Rule::exists('invitations', 'id')->where('wedding_id', $weddingId),
            ],
        ];
    }
}
