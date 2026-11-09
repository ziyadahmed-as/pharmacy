<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const EXPIRED = 'expired';

    public const CANCELLED = 'cancelled';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'eligible_roles' => 'array',
        'expires_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function concernedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concerns_user_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::PENDING);
    }

    /** Pending, not expired, role eligible, and the user is neither initiator nor the person concerned. */
    public function scopeDecidableBy(Builder $query, User $user): Builder
    {
        $roles = array_map(fn ($r) => $r->value, $user->roleEnums());

        return $query->pending()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where('initiator_id', '!=', $user->id)
            ->where(fn ($q) => $q->whereNull('concerns_user_id')->orWhere('concerns_user_id', '!=', $user->id))
            ->where(function ($q) use ($roles) {
                foreach ($roles as $role) {
                    $q->orWhereJsonContains('eligible_roles', $role);
                }
            });
    }
}
