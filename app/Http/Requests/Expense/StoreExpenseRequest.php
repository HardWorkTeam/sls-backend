<?php

namespace App\Http\Requests\Expense;

use App\Enums\ExpenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'item_name' => ['required', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'in:USD,KHR'],
            'paid_amount' => ['nullable', 'numeric', 'min:0', 'lte:amount'],
            'status' => ['nullable', Rule::enum(ExpenseStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'spent_at' => ['nullable', 'date'],
        ];
    }
}
