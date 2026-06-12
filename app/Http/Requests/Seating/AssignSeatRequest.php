<?php

namespace App\Http\Requests\Seating;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSeatRequest extends FormRequest
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
            'guest_id' => [
                'required',
                Rule::exists('guests', 'id')->where('wedding_id', $weddingId),
            ],
            'wedding_table_id' => [
                'required',
                Rule::exists('wedding_tables', 'id')->where('wedding_id', $weddingId),
            ],
            'seat_number' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
