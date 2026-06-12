<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['wedding_id', 'guest_id', 'wedding_table_id', 'seat_number'])]
class GuestSeating extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'seat_number' => 'integer',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(WeddingTable::class, 'wedding_table_id');
    }
}
