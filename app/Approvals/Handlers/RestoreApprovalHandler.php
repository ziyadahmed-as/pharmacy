<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\RestoreRequest;
use App\Models\User;
use App\Services\BackupService;
use Throwable;

/**
 * Handles approval decisions for backup restoration (UC-37, UC-34).
 *
 * Enforces:
 * - PM or Owner approval required (E2, AC-21).
 * - SA can NEVER approve restore requests.
 * - Initiator cannot approve (E1).
 * - Executes backup restoration on approval and handles rollback if anything fails.
 *
 * @uc UC-37, UC-34
 *
 * @fr FR-9.6, FR-8.10
 *
 * @ac AC-21
 */
class RestoreApprovalHandler implements ApprovalHandler
{
    public function __construct(
        private readonly BackupService $backupService
    ) {}

    public function type(): string
    {
        return 'backup.restore';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<Role>
     */
    public function eligibleApproverRoles(User $initiator, array $payload): array
    {
        // SA can never approve. PM or Owner decides.
        if ($initiator->hasRole(Role::PharmacyManager)) {
            return [Role::Owner];
        }

        if ($initiator->hasRole(Role::Owner)) {
            return [Role::PharmacyManager];
        }

        return [Role::PharmacyManager, Role::Owner];
    }

    /**
     * @throws Throwable
     */
    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $restoreRequestId = $request->payload['restore_request_id'] ?? null;
        /** @var RestoreRequest $restoreRequest */
        $restoreRequest = RestoreRequest::findOrFail($restoreRequestId);

        if ($restoreRequest->isApproved() || $restoreRequest->isCompleted()) {
            return;
        }

        $restoreRequest->update([
            'status' => RestoreRequest::STATUS_APPROVED,
            'approved_by' => $approver->id,
        ]);

        $this->backupService->restore($restoreRequest, $approver);
    }

    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        $restoreRequestId = $request->payload['restore_request_id'] ?? null;
        /** @var RestoreRequest $restoreRequest */
        $restoreRequest = RestoreRequest::findOrFail($restoreRequestId);

        $restoreRequest->update([
            'status' => RestoreRequest::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'error_message' => $request->decision_comment,
        ]);
    }
}
