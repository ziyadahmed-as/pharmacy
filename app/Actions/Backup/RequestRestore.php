<?php

namespace App\Actions\Backup;

use App\Models\BackupRun;
use App\Models\RestoreRequest;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Action to initiate a backup restoration request (UC-37, UC-34).
 *
 * @uc UC-37, UC-34
 *
 * @fr FR-9.6, FR-8.10
 *
 * @ac AC-21
 */
class RequestRestore
{
    public function __construct(
        private readonly ApprovalService $approvalService
    ) {}

    /**
     * @param  array{
     *     backup_filename: string,
     *     reason: string,
     * }  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $user, array $data): RestoreRequest
    {
        if (! $user->isSystemAdmin()) {
            throw new AuthorizationException(__('backup.unauthorized_restore'));
        }

        $filename = trim((string) ($data['backup_filename'] ?? ''));
        if ($filename === '') {
            throw ValidationException::withMessages([
                'backup_filename' => __('backup.filename_required'),
            ]);
        }

        $diskName = (string) config('backup.disk', 'backup');
        if (! Storage::disk($diskName)->exists($filename)) {
            throw ValidationException::withMessages([
                'backup_filename' => __('backup.file_not_found', ['file' => $filename]),
            ]);
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => __('backup.reason_required'),
            ]);
        }

        $backupRun = BackupRun::where('filename', $filename)->first();

        $restoreRequest = RestoreRequest::create([
            'backup_run_id' => $backupRun?->id,
            'backup_filename' => $filename,
            'reason' => $reason,
            'status' => RestoreRequest::STATUS_PENDING,
            'requested_by' => $user->id,
        ]);

        $summary = sprintf('Data restore request for archive %s by System Admin', $filename);

        $approval = $this->approvalService->request(
            type: 'backup.restore',
            initiator: $user,
            payload: [
                'restore_request_id' => $restoreRequest->id,
                'backup_filename' => $filename,
                'reason' => $reason,
            ],
            summary: $summary,
            subject: $restoreRequest,
            comment: $reason
        );

        $restoreRequest->update(['approval_request_id' => $approval->id]);

        AuditLogger::record(
            action: 'backup.restore.requested',
            subject: $restoreRequest,
            old: [],
            new: [
                'backup_filename' => $filename,
                'approval_request_id' => $approval->id,
            ],
            metadata: ['reason' => $reason],
            user: $user,
            description: "Data restoration requested for archive {$filename}"
        );

        return $restoreRequest;
    }
}
