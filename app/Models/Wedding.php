<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Support\PlanCapabilities;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'wedding_code',
    'wedding_name',
    'bride_name',
    'groom_name',
    'bride_photo_path',
    'groom_photo_path',
    'phone',
    'email',
    'wedding_date',
    'wedding_time',
    'ceremony_venue',
    'reception_venue',
    'google_map_link',
    'story_description',
    'status',
    'published_at',
    'completed_at',
    'cancelled_at',
    'package_id',
    'created_by_user_id',
])]
class Wedding extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'wedding_date' => 'date',
            'wedding_time' => 'string',
            'published_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The wedding's most recent PAID subscription — the one that decides what
     * the wedding is allowed to do (see {@see PlanCapabilities}).
     *
     * Modelled as a relation rather than an ad-hoc query so Eloquent's own
     * relation cache applies: the plan is consulted several times per request
     * (the plan.module middleware, then again inside the services), and each
     * of those used to be a fresh round trip to Postgres.
     */
    public function paidSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->where('status', SubscriptionStatus::Paid->value),
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WeddingMember::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function guestGroups(): HasMany
    {
        return $this->hasMany(GuestGroup::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(WeddingTable::class);
    }

    public function rsvpResponses(): HasMany
    {
        return $this->hasMany(RsvpResponse::class);
    }

    public function gifts(): HasMany
    {
        return $this->hasMany(Gift::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function mediaItems(): HasMany
    {
        return $this->hasMany(MediaItem::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(TimelineEvent::class);
    }

    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(WeddingReminder::class);
    }
}
