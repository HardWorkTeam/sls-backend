<?php

namespace App\Models;

use App\Enums\WeddingReminderMilestone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A countdown reminder that has been (or is being) emailed to a couple.
 *
 * One row per wedding per milestone per wedding date — see the unique index in
 * the migration. Rows are written *before* the mail goes out to claim the send.
 */
#[Fillable(['wedding_id', 'milestone', 'wedding_date', 'recipients', 'sent_at'])]
class WeddingReminder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'milestone' => WeddingReminderMilestone::class,
            'wedding_date' => 'date',
            'recipients' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }
}
