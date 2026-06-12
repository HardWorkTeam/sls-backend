<?php

namespace App\Http\Requests\Seating;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
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
        $tableId = $this->route('table')?->id;

        return [
            'table_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('wedding_tables', 'table_name')->where('wedding_id', $weddingId)->ignore($tableId),
            ],
            'table_number' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::unique('wedding_tables', 'table_number')->where('wedding_id', $weddingId)->ignore($tableId),
            ],
            'capacity' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000'],
            'layout' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
