<?php

namespace App\Http\Requests\Wedding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWeddingRequest extends FormRequest
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
            'wedding_name' => ['sometimes', 'required', 'string', 'max:255'],
            'bride_name' => ['sometimes', 'required', 'string', 'max:255'],
            'groom_name' => ['sometimes', 'required', 'string', 'max:255'],
            'bride_photo_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'groom_photo_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'wedding_date' => ['sometimes', 'nullable', 'date'],
            'wedding_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'ceremony_venue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'reception_venue' => ['sometimes', 'nullable', 'string', 'max:255'],
            'google_map_link' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'story_description' => ['sometimes', 'nullable', 'string'],
            'package_id' => ['sometimes', 'nullable', Rule::exists('packages', 'id')],
        ];
    }
}
