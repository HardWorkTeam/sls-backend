<?php

namespace App\Models;

use App\Enums\RoleKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'avatar_path', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function weddingMembers(): HasMany
    {
        return $this->hasMany(WeddingMember::class);
    }

    public function uploadedMediaItems(): HasMany
    {
        return $this->hasMany(MediaItem::class, 'uploaded_by_user_id');
    }

    public function hasRole(RoleKey|string $role): bool
    {
        $key = $role instanceof RoleKey ? $role->value : $role;

        return $this->roles->contains('key', $key);
    }

    /**
     * @param  list<RoleKey|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleKey::SuperAdmin);
    }

    public function hasPermission(string $permissionKey): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('key', $permissionKey))
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function roleKeys(): array
    {
        return $this->roles->pluck('key')->all();
    }
}
