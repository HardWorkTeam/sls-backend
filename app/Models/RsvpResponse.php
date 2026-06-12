<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'wedding_id',
    'invitation_id',
    'guest_id',
    'guest_name',
    'phone',
    'number_of_guests',
    'message',
    'status',
    'responded_at',
])]
class RsvpResponse extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'number_of_guests' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
}
