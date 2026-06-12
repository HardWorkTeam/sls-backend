<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'wedding_id' => $this->wedding_id,
            'title' => $this->title,
            'body' => $this->body,
            'channel' => $this->channel,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_by' => UserResource::make($this->whenLoaded('createdBy')),
            'notification_logs_count' => $this->whenCounted('notificationLogs'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
