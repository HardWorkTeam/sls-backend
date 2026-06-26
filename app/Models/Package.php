<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'price', 'currency', 'features', 'capabilities', 'is_active'])]
class Package extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'capabilities' => 'array',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function weddings(): HasMany
    {
        return $this->hasMany(Wedding::class);
    }

    /**
     * A free plan ($0 or no price). Free plans activate the moment a couple
     * selects them — no payment step or admin confirmation.
     */
    public function isFree(): bool
    {
        return (float) ($this->price ?? 0) <= 0;
    }
}
