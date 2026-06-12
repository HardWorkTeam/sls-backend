<?php

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlbumRequest extends FormRequest
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
        $albumId = $this->route('album')?->id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('albums', 'name')->where('wedding_id', $weddingId)->whereNull('deleted_at')->ignore($albumId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
