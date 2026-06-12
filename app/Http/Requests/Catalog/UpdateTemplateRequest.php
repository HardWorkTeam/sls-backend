<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTemplateRequest extends FormRequest
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
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('invitation_templates', 'slug')->ignore($this->route('template')?->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'preview_image_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'config' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
