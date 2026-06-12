<?php

namespace App\Http\Requests\Wedding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeddingRequest extends FormRequest
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
            'wedding_name' => ['required', 'string', 'max:255'],
            'bride_name' => ['required', 'string', 'max:255'],
            'groom_name' => ['required', 'string', 'max:255'],
            'bride_photo_path' => ['nullable', 'string', 'max:2048'],
            'groom_photo_path' => ['nullable', 'string', 'max:2048'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'wedding_date' => ['nullable', 'date'],
            'wedding_time' => ['nullable', 'date_format:H:i'],
            'ceremony_venue' => ['nullable', 'string', 'max:255'],
            'reception_venue' => ['nullable', 'string', 'max:255'],
            'google_map_link' => ['nullable', 'url', 'max:2048'],
            'story_description' => ['nullable', 'string'],
            'package_id' => ['nullable', Rule::exists('packages', 'id')],
        ];
    }
}
