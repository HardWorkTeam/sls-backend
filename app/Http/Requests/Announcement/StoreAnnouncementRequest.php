<?php

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'channel' => ['required', Rule::enum(AnnouncementChannel::class)],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
