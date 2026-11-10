<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\QuarantineRecord;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockMovementService;
use RuntimeException;

/**
 * Handles approval decisions for releasing stock from quarantine (UC-08 Step 4).
 *
 * @uc UC-08 @fr FR-2.15 @br BR-01, BR-10 @ac AC-07
 */
class QuarantineReleaseHandler implements ApprovalHandler
{
    public function type(): string
    {
        return 'stock.quarantine.release';
    }

    public function eligibleApproverRoles(User $initiator, array $payload): array
    {
        return [Role::PharmacyManager, Role::Owner];
    }

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $recordId = $request->payload['quarantine_record_id'] ?? null;
        $record = QuarantineRecord::with('batch.product')->findOrFail($recordId);

        if ($record->isRecalled()) {
            throw new RuntimeException(__('catalogue.recalled_cannot_be_released'));
        }

        if (! $record->isOpen()) {
            throw new RuntimeException(__('catalogue.quarantine_record_not_open'));
        }

        $releaseReason = $request->payload['release_reason'] ?? $request->initiator_comment ?? 'Approved release';

        // 1) Deduct from quarantine
        StockMovementService::post(
            batch: $record->batch,
            signedQuantity: -$record->quantity,
            type: 'quarantine',
            source: $record,
            user: $approver,
            status: 'quarantine',
            description: "Released from quarantine: {$releaseReason}"
        );

        // 2) Add back to sellable
        StockMovementService::post(
            batch: $record->batch,
            signedQuantity: $record->quantity,
            type: 'quarantine',
            source: $record,
            user: $approver,
            status: 'sellable',
            description: "Released from quarantine: {$releaseReason}"
        );

        $record->update([
            'status' => QuarantineRecord::STATUS_RELEASED,
        ]);

        AuditLogger::record(
            'stock.quarantine.released',
            $record,
            ['status' => QuarantineRecord::STATUS_OPEN],
            ['status' => QuarantineRecord::STATUS_RELEASED],
            [
                'quantity' => $record->quantity,
                'approver_id' => $approver->id,
                'release_reason' => $releaseReason,
            ],
            $approver
        );
    }

    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        // Remains in open status
    }
}
