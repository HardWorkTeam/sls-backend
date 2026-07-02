<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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
    'check_in_token',
    'check_in_code',
    'checked_in_at',
])]
class Guest extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Alphabet for short check-in codes — uppercase letters and digits with the
     * visually ambiguous characters (0/O, 1/I, etc.) removed so codes read
     * cleanly off a screen and are easy to type.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected static function booted(): void
    {
        static::creating(function (Guest $guest): void {
            // Every guest carries an opaque token for their personal check-in QR.
            $guest->check_in_token ??= (string) Str::ulid();

            // ...plus a short, human-friendly code, unique within the wedding,
            // for typing in by hand at the door.
            if ($guest->check_in_code === null && $guest->wedding_id !== null) {
                $guest->check_in_code = static::uniqueCheckInCode((int) $guest->wedding_id);
            }
        });
    }

    /**
     * Generate a random short check-in code (6 unambiguous characters).
     */
    public static function randomCheckInCode(int $length = 6): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * A short check-in code guaranteed not to collide with an existing guest in
     * the same wedding.
     */
    public static function uniqueCheckInCode(int $weddingId): string
    {
        do {
            $code = static::randomCheckInCode();
        } while (
            static::withTrashed()
                ->where('wedding_id', $weddingId)
                ->where('check_in_code', $code)
                ->exists()
        );

        return $code;
    }

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'checked_in_at' => 'datetime',
        ];
    }

    public function isCheckedIn(): bool
    {
        return $this->checked_in_at !== null;
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
