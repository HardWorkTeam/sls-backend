<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'wedding_id',
    'album_id',
    'uploaded_by_user_id',
    'media_type',
    'storage_path',
    'thumbnail_path',
    'original_name',
    'mime_type',
    'size_bytes',
    'is_public',
    'taken_at',
])]
class MediaItem extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_public' => 'boolean',
            'taken_at' => 'datetime',
        ];
    }

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
