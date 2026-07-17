<?php

namespace App\Http\Requests\Gallery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

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
                File::types([
                    'jpg',
                    'jpeg',
                    'png',
                    'webp',
                    'avif',
                    'mp4',
                    'webm',
                    'mov',
                ])->max(50 * 1024),
            ],
            'album_id' => [
                'nullable',
                Rule::exists('albums', 'id')->where('wedding_id', $weddingId),
            ],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
