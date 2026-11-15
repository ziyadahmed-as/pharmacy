<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\Recall;
use App\Models\User;
use App\Services\AuditLogger;
use RuntimeException;

/**
 * Handles approval decisions for closing a product/batch recall (UC-10 Step 3).
 *
 * @uc UC-10 @fr FR-2.11, FR-2.12
 */
class CloseRecallHandler implements ApprovalHandler
{
    public function type(): string
    {
        return 'stock.recall.close';
    }

    public function eligibleApproverRoles(User $initiator, array $payload): array
    {
        return [Role::PharmacyManager, Role::Owner];
    }

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $recallId = $request->payload['recall_id'] ?? null;
        $recall = Recall::with('batches')->findOrFail($recallId);

        if (! $recall->isOpen()) {
            throw new RuntimeException(__('recalls.recall_already_closed'));
        }

        $closeComment = $request->payload['close_comment'] ?? $request->initiator_comment ?? 'Recall closed and verified';

        $recall->update([
            'status' => Recall::STATUS_CLOSED,
            'closed_at' => now(),
        ]);

        // Mark actions on recall batches if specified
        $finalAction = $request->payload['final_batch_action'] ?? 'completed';
        foreach ($recall->batches as $rb) {
            $rb->update([
                'action' => $finalAction,
            ]);
        }

        AuditLogger::record(
            'stock.recall.closed',
            $recall,
            ['status' => Recall::STATUS_OPEN],
            ['status' => Recall::STATUS_CLOSED],
            [
                'recall_no' => $recall->recall_no,
                'approver_id' => $approver->id,
                'close_comment' => $closeComment,
                'closed_at' => (string) $recall->closed_at,
            ],
            $approver,
            "Recall {$recall->recall_no} closed by approver {$approver->name}"
        );
    }

    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        // Recall remains in open status
    }
}
