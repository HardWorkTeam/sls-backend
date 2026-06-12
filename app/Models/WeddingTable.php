<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['wedding_id', 'table_name', 'table_number', 'capacity', 'layout'])]
class WeddingTable extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'layout' => 'array',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function seatings(): HasMany
    {
        return $this->hasMany(GuestSeating::class);
    }
}
