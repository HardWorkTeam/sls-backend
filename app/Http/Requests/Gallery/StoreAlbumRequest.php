<?php

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlbumRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('albums', 'name')->where('wedding_id', $weddingId)->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
