<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageRequest extends FormRequest
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
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('packages', 'name')->ignore($this->route('package')?->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'features' => ['sometimes', 'nullable', 'array'],
            'capabilities' => ['sometimes', 'nullable', 'array'],
            'capabilities.modules' => ['sometimes', 'array'],
            'capabilities.modules.seating' => ['sometimes', 'boolean'],
            'capabilities.modules.gallery' => ['sometimes', 'boolean'],
            'capabilities.modules.gifts' => ['sometimes', 'boolean'],
            'capabilities.guest_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'capabilities.invitation_design_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
