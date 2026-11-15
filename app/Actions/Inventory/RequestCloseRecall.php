<?php

namespace App\Actions\Inventory;

use App\Models\ApprovalRequest;
use App\Models\Recall;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Action to request closing a recall via the approval engine (UC-10 Step 3).
 */
class RequestCloseRecall
{
    public function __construct(
        private readonly ApprovalService $approvalService
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $user,
        Recall $recall,
        ?string $comment = null,
        string $finalBatchAction = 'completed'
    ): ApprovalRequest {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            throw new AuthorizationException(__('recalls.unauthorized'));
        }

        if (! $user->hasPermission('stock.dispose') && ! $user->hasPermission('catalogue.manage')) {
            throw new AuthorizationException(__('recalls.unauthorized'));
        }

        if (! $recall->isOpen()) {
            throw ValidationException::withMessages([
                'recall' => __('recalls.recall_already_closed'),
            ]);
        }

        $summary = sprintf(
            'Close product recall %s for %s (%s)',
            $recall->recall_no,
            $recall->product->brand,
            $recall->severity
        );

        $request = $this->approvalService->request(
            type: 'stock.recall.close',
            initiator: $user,
            payload: [
                'recall_id' => $recall->id,
                'recall_no' => $recall->recall_no,
                'product_id' => $recall->product_id,
                'final_batch_action' => $finalBatchAction,
                'close_comment' => $comment,
            ],
            summary: $summary,
            subject: $recall,
            comment: $comment
        );

        AuditLogger::record(
            action: 'stock.recall.close_requested',
            subject: $recall,
            old: [],
            new: [
                'approval_request_id' => $request->id,
            ],
            metadata: [
                'recall_no' => $recall->recall_no,
                'comment' => $comment,
            ],
            user: $user,
            description: "Close requested for Recall {$recall->recall_no}"
        );

        return $request;
    }
}
