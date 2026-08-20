<?php

namespace App\Http\Requests\Guest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkGroupRequest extends FormRequest
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
            'guest_ids' => ['nullable', 'array'],
            'guest_ids.*' => ['integer'],
            'guest_group_id' => [
                'required',
                Rule::exists('guest_groups', 'id')->where('wedding_id', $weddingId),
            ],
        ];
    }
}
