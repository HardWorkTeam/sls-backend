<?php

namespace App\Http\Requests\Rsvp;

use App\Enums\RsvpStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRsvpRequest extends FormRequest
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
            'guest_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'number_of_guests' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'message' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', Rule::enum(RsvpStatus::class)],
        ];
    }
}
