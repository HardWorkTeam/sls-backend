<?php

namespace App\Http\Requests\Seating;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'table_name'   => ['sometimes', 'required', 'string', 'max:255'],
            'table_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'capacity'     => ['sometimes', 'required', 'integer', 'min:0', 'max:1000'],
            'layout'       => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * After standard validation passes, check that the combination of
     * table_name + table_number is unique for this wedding (excluding self).
     * A table is only a duplicate when BOTH fields match an existing row.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $weddingId = $this->route('wedding')?->id;
                $tableId   = $this->route('table')?->id;

                // Resolve effective name / number (fall back to existing values
                // when the field was not sent in a partial update).
                $existing = \DB::table('wedding_tables')->where('id', $tableId)->first();
                $name     = $this->has('table_name')   ? $this->input('table_name')   : ($existing->table_name   ?? null);
                $number   = $this->has('table_number') ? $this->input('table_number') : ($existing->table_number ?? null);

                $clash = \DB::table('wedding_tables')
                    ->where('wedding_id', $weddingId)
                    ->where('id', '!=', $tableId)
                    ->where('table_name', $name)
                    ->where(function ($q) use ($number) {
                        if ($number === null) {
                            $q->whereNull('table_number');
                        } else {
                            $q->where('table_number', (int) $number);
                        }
                    })
                    ->exists();

                if ($clash) {
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
