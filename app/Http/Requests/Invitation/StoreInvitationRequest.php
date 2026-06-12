<?php

namespace App\Http\Requests\Invitation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
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
            'invitation_template_id' => ['nullable', Rule::exists('invitation_templates', 'id')],
            'title' => ['nullable', 'string', 'max:255'],
            'cover_image_path' => ['nullable', 'string', 'max:2048'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
