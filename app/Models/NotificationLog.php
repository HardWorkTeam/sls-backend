<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'announcement_id',
    'recipient_user_id',
    'recipient_guest_id',
    'channel',
    'status',
    'provider_message_id',
    'error_message',
    'sent_at',
])]
class NotificationLog extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function recipientGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'recipient_guest_id');
    }
}
