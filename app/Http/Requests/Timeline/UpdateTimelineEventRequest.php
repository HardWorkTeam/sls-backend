<?php

namespace App\Http\Requests\Timeline;

use App\Enums\TimelineCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimelineEventRequest extends FormRequest
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
            'category' => ['sometimes', Rule::enum(TimelineCategory::class)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'google_map_link' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}
