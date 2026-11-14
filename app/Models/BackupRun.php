<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Backup Run record (UC-37).
 *
 * @property int $id
 * @property string $filename
 * @property string $disk
 * @property string $type
 * @property int $size_bytes
 * @property string|null $checksum
 * @property string $status
 * @property string|null $error_message
 * @property bool $is_encrypted
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $completed_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 *
 * @uc UC-37
 *
 * @fr FR-9.6, FR-8.10
 *
 * @nfr NFR-4, NFR-2
 *
 * @ac AC-21, AC-35
 */
class BackupRun extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const TYPE_FULL = 'full';

    public const TYPE_DB = 'db';

    protected $fillable = [
        'filename',
        'disk',
        'type',
        'size_bytes',
        'checksum',
        'status',
        'error_message',
        'is_encrypted',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_encrypted' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function restoreRequests(): HasMany
    {
        return $this->hasMany(RestoreRequest::class);
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function formattedSize(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}
