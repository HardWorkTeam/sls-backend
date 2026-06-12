<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'name', 'preview_image_path', 'config', 'is_active'])]
class InvitationTemplate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
