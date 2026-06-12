<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'wedding_id',
    'guest_group_id',
    'invitation_id',
    'name',
    'phone',
    'email',
    'address',
    'note',
    'is_vip',
])]
class Guest extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GuestGroup::class, 'guest_group_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    public function rsvpResponses(): HasMany
    {
        return $this->hasMany(RsvpResponse::class);
    }

    public function seating(): HasOne
    {
        return $this->hasOne(GuestSeating::class);
    }

    public function gifts(): HasMany
    {
        return $this->hasMany(Gift::class);
    }
}
