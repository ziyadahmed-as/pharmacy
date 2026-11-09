<?php

namespace App\Models;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function isRole(RoleEnum $role): bool
    {
        return $this->slug === $role->value;
    }
}
