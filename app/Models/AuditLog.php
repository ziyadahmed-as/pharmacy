<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'event_category',
        'description',
        'ip_address',
        'user_agent',
        'session_identifier',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * User associated with the audit event (if authenticated).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Prevent updates to audit log entries (Immutability guarantee).
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new RuntimeException('Audit log entries are immutable and cannot be updated.');
        }

        return parent::save($options);
    }

    /**
     * Prevent deletion of audit log entries (Immutability guarantee).
     */
    public function delete(): ?bool
    {
        throw new RuntimeException('Audit log entries are immutable and cannot be deleted.');
    }
}

