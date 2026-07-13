<?php

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMediaRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:51200',
            ],
            'album_id' => [
                'nullable',
                Rule::exists('albums', 'id')->where('wedding_id', $weddingId),
            ],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
