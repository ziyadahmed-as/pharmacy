<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Append-only (BR-15). Eloquent refuses updates and deletes; database triggers
 * refuse them for the query builder and raw SQL too.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => self::immutable('updated'));
        static::deleting(fn () => self::immutable('deleted'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            self::immutable('updated');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        self::immutable('deleted');
    }

    public function forceDelete(): ?bool
    {
        self::immutable('deleted');
    }

    public static function destroy($ids): int
    {
        self::immutable('deleted');
    }

    public static function truncate(): void
    {
        self::immutable('deleted');
    }

    private static function immutable(string $verb): never
    {
        throw new RuntimeException("Audit log entries are immutable and cannot be {$verb}.");
    }
}
