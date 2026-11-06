<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'admin_id',
        'verification_method',
        'outcome',
        'notes',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    /**
     * The target user whose identity was verified.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The administrator who performed the verification.
     */
    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Check if verification was successful.
     */
    public function isVerified(): bool
    {
        return $this->outcome === 'verified';
    }
}
