<?php

namespace App\Http\Requests\Rsvp;

use App\Enums\RsvpStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicRsvpRequest extends FormRequest
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
            'guest_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'number_of_guests' => ['required', 'integer', 'min:1', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(RsvpStatus::submittable())],
        ];
    }
}
