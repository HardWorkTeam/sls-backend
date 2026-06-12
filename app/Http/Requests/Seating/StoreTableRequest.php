<?php

namespace App\Http\Requests\Seating;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTableRequest extends FormRequest
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
            'table_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('wedding_tables', 'table_name')->where('wedding_id', $weddingId),
            ],
            'table_number' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('wedding_tables', 'table_number')->where('wedding_id', $weddingId),
            ],
            'capacity' => ['required', 'integer', 'min:0', 'max:1000'],
            'layout' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
