<?php

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            'channel' => ['sometimes', Rule::enum(AnnouncementChannel::class)],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
