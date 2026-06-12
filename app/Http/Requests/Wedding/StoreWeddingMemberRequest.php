<?php

namespace App\Http\Requests\Wedding;

use App\Enums\MemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWeddingMemberRequest extends FormRequest
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
            'user_id' => ['required', Rule::exists('users', 'id')],
            'member_role' => ['required', Rule::enum(MemberRole::class)],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }
}
