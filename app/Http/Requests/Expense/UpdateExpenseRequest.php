<?php

namespace App\Http\Requests\Expense;

use App\Enums\ExpenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseRequest extends FormRequest
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
            'item_name' => ['sometimes', 'string', 'max:255'],
            'vendor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'paid_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::enum(ExpenseStatus::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'spent_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
