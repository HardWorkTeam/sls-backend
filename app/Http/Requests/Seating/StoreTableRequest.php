<?php

namespace App\Http\Requests\Seating;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

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
        return [
            'table_name'   => ['required', 'string', 'max:255'],
            'table_number' => ['nullable', 'integer', 'min:1'],
            'capacity'     => ['required', 'integer', 'min:0', 'max:1000'],
            'layout'       => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * After standard validation passes, check that the combination of
     * table_name + table_number is unique for this wedding.
     * A table is only a duplicate when BOTH fields match an existing row.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $weddingId  = $this->route('wedding')?->id;
                $name       = $this->input('table_name');
                $number     = $this->input('table_number');

                $exists = \DB::table('wedding_tables')
                    ->where('wedding_id', $weddingId)
                    ->where('table_name', $name)
                    ->where(function ($q) use ($number) {
                        if ($number === null) {
                            $q->whereNull('table_number');
                        } else {
                            $q->where('table_number', (int) $number);
                        }
                    })
                    ->exists();

                if ($exists) {
                    $label = $number !== null ? "\"{$name}\" #". (int) $number : "\"{$name}\"";
                    $validator->errors()->add(
                        'table_name',
                        "A table {$label} already exists for this wedding."
                    );
                }
            },
        ];
    }
}
