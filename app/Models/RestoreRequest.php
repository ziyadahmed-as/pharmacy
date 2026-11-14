<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Restore Request record (UC-37).
 *
 * @property int $id
 * @property int|null $backup_run_id
 * @property string $backup_filename
 * @property string $reason
 * @property string $status
 * @property int $requested_by
 * @property int|null $approval_request_id
 * @property int|null $approved_by
 * @property string|null $safety_backup_filename
 * @property string|null $error_message
 * @property array|null $log_entries
 * @property CarbonInterface|null $restored_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read BackupRun|null $backupRun
 * @property-read User $requestedBy
 * @property-read User|null $approvedBy
 * @property-read ApprovalRequest|null $approvalRequest
 *
 * @uc UC-37
 *
 * @fr FR-9.6, FR-8.10
 *
 * @nfr NFR-4, NFR-2
 *
 * @ac AC-21, AC-35
 */
class RestoreRequest extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RESTORING = 'restoring';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'backup_run_id',
        'backup_filename',
        'reason',
        'status',
        'requested_by',
        'approval_request_id',
        'approved_by',
        'safety_backup_filename',
        'error_message',
        'log_entries',
        'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'log_entries' => 'array',
            'restored_at' => 'datetime',
        ];
    }

    public function backupRun(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function appendLog(string $message): void
    {
        $logs = $this->log_entries ?? [];
        $logs[] = [
            'timestamp' => now()->toIso8601String(),
            'message' => $message,
        ];
        $this->log_entries = $logs;
        $this->save();
    }
}
