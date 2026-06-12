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
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm',
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
