<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'wedding_id',
    'invitation_template_id',
    'invitation_code',
    'title',
    'cover_image_path',
    'settings',
    'status',
    'published_at',
])]
class Invitation extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InvitationTemplate::class, 'invitation_template_id');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function rsvpResponses(): HasMany
    {
        return $this->hasMany(RsvpResponse::class);
    }
}
