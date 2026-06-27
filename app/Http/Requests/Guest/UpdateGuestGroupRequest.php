<?php

namespace App\Http\Requests\Guest;

use App\Enums\GuestGroupType;
use App\Models\GuestGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuestGroupRequest extends FormRequest
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
        $groupId = $this->route('group')?->id;

        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                // Group names are unique within a single wedding, case-insensitively,
                // ignoring this group.
                function (string $attribute, mixed $value, \Closure $fail) use ($weddingId, $groupId): void {
                    $exists = GuestGroup::query()
                        ->where('wedding_id', $weddingId)
                        ->whereKeyNot($groupId)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                        ->exists();

                    if ($exists) {
                        $fail('A group with this name already exists.');
                    }
                },
            ],
            'type' => ['sometimes', Rule::enum(GuestGroupType::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
