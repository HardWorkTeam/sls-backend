<?php

namespace App\Http\Requests\Invitation;

use App\Enums\InvitationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvitationRequest extends FormRequest
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
            'invitation_template_id' => ['sometimes', 'nullable', Rule::exists('invitation_templates', 'id')],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cover_image_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'settings' => ['sometimes', 'nullable', 'array'],
            'status' => ['sometimes', Rule::enum(InvitationStatus::class)],
        ];
    }
}
