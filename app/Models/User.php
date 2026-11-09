<?php

namespace App\Models;

use App\Enums\Role as RoleEnum;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_DEACTIVATED = 'deactivated';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name', 'username', 'email', 'locale', 'password',
        'is_active', 'account_status',
        'failed_login_attempts', 'locked_at',
        'must_change_password', 'password_changed_at', 'password_reset_at',
        'two_factor_enabled', 'two_factor_secret',
        'last_login_at', 'last_login_ip', 'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'failed_login_attempts' => 'integer',
            'locked_at' => 'datetime',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'password_reset_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'last_login_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function identityVerifications(): HasMany
    {
        return $this->hasMany(IdentityVerification::class);
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** Canonical state: deactivated, pending_approval, locked or active. */
    public function getStatusAttribute(): string
    {
        if ($this->account_status === self::STATUS_REJECTED) {
            return self::STATUS_REJECTED;
        }

        if (! $this->is_active && $this->account_status !== self::STATUS_PENDING) {
            return self::STATUS_DEACTIVATED;
        }

        if ($this->account_status === self::STATUS_PENDING) {
            return self::STATUS_PENDING;
        }

        if ($this->account_status === self::STATUS_DEACTIVATED) {
            return self::STATUS_DEACTIVATED;
        }

        return $this->isLocked() ? 'locked' : self::STATUS_ACTIVE;
    }

    /** An account may sign in only when active and not pending approval. */
    public function canSignIn(): bool
    {
        return $this->is_active && $this->account_status !== self::STATUS_PENDING;
    }

    public function isEligibleForPasswordReset(): bool
    {
        return in_array($this->status, ['active', 'locked'], true);
    }

    public function isEligibleForUnlock(): bool
    {
        return $this->status === 'locked';
    }

    /** @return list<RoleEnum> */
    public function roleEnums(): array
    {
        $out = [];
        foreach ($this->roles as $role) {
            $enum = RoleEnum::tryFrom($role->slug);
            if ($enum) {
                $out[] = $enum;
            }
        }

        // stable order: SA, OW, PM, PS
        usort($out, fn ($a, $b) => array_search($a, RoleEnum::cases(), true) <=> array_search($b, RoleEnum::cases(), true));

        return $out;
    }

    public function hasRole(RoleEnum|string $role): bool
    {
        $slug = $role instanceof RoleEnum ? $role->value : $role;

        return $this->roles->contains('slug', $slug);
    }

    /** @param  array<RoleEnum|string>  $roles */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /** Permissions add up across the roles a person holds (AGENTS.md section 5). */
    public function hasPermission(string $permission): bool
    {
        foreach ($this->roleEnums() as $role) {
            if (in_array($permission, Permissions::forRole($role), true)) {
                return true;
            }
        }

        return false;
    }

    public function requiresMfa(): bool
    {
        if (! $this->two_factor_enabled) {
            return false;
        }

        foreach ($this->roleEnums() as $role) {
            if ($role->allowsMfa()) {
                return true;
            }
        }

        return false;
    }

    /** Patient, prescription, sales, cost and financial data: never the SA. */
    public function canAccessBusinessData(): bool
    {
        foreach ($this->roleEnums() as $role) {
            if ($role->hasBusinessDataAccess()) {
                return true;
            }
        }

        return false;
    }
}
