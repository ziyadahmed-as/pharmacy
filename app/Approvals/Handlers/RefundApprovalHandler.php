<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\QuarantineRecord;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Handles approval decisions for customer return & refund exceeding limit (UC-16).
 *
 * @uc UC-16 @fr FR-3.8 @br BR-18 @ac AC-15
 */
class RefundApprovalHandler implements ApprovalHandler
{
    public function type(): string
    {
        return 'sale.refund';
    }

    public function eligibleApproverRoles(User $initiator, array $payload): array
    {
        return [Role::PharmacyManager, Role::Owner];
    }

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $returnId = $request->payload['sale_return_id'] ?? null;
        /** @var SaleReturn $saleReturn */
        $saleReturn = SaleReturn::with(['lines.batch', 'sale'])->findOrFail($returnId);

        if ($saleReturn->isCompleted()) {
            throw new RuntimeException(__('sales.refund_already_completed'));
        }

        DB::transaction(function () use ($saleReturn, $approver, $request) {
            // 1. Move stock based on line assessment
            foreach ($saleReturn->lines as $line) {
                if ($line->isResaleable()) {
                    StockMovementService::post(
                        batch: $line->batch,
                        signedQuantity: $line->quantity,
                        type: 'return',
                        source: $saleReturn,
                        user: $approver,
                        status: 'sellable',
                        description: "Customer return (resaleable): {$saleReturn->refund_no}"
                    );
                } else {
                    $quarantine = QuarantineRecord::create([
                        'batch_id' => $line->batch_id,
                        'quantity' => $line->quantity,
                        'reason' => QuarantineRecord::REASON_DAMAGED,
                        'status' => QuarantineRecord::STATUS_OPEN,
                        'created_by' => $approver->id,
                        'note' => "Customer return assessed damaged/not resaleable ({$saleReturn->refund_no}): ".($line->reason ?? 'Customer return'),
                    ]);

                    StockMovementService::post(
                        batch: $line->batch,
                        signedQuantity: $line->quantity,
                        type: 'quarantine',
                        source: $quarantine,
                        user: $approver,
                        status: 'quarantine',
                        description: "Customer return (quarantine): {$saleReturn->refund_no}"
                    );
                }
            }

            // 2. Mark completed
            $saleReturn->forceFill([
                'status' => SaleReturn::STATUS_COMPLETED,
                'completed_at' => now(),
            ])->save();

            AuditLogger::record(
                action: 'sale.refund.approved',
                subject: $saleReturn,
                old: ['status' => SaleReturn::STATUS_PENDING_APPROVAL],
                new: ['status' => SaleReturn::STATUS_COMPLETED],
                metadata: [
                    'refund_no' => $saleReturn->refund_no,
                    'total_refund' => $saleReturn->total_refund,
                    'approver_id' => $approver->id,
                    'initiator_id' => $request->initiator_id,
                ],
                user: $approver,
                description: "Refund {$saleReturn->refund_no} approved by {$approver->username}"
            );
        });
    }

    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        $returnId = $request->payload['sale_return_id'] ?? null;
        $saleReturn = SaleReturn::find($returnId);

        if ($saleReturn && $saleReturn->isPendingApproval()) {
            $saleReturn->forceFill([
                'status' => SaleReturn::STATUS_REJECTED,
            ])->save();

            AuditLogger::record(
                action: 'sale.refund.rejected',
                subject: $saleReturn,
                old: ['status' => SaleReturn::STATUS_PENDING_APPROVAL],
                new: ['status' => SaleReturn::STATUS_REJECTED],
                metadata: [
                    'refund_no' => $saleReturn->refund_no,
                    'approver_id' => $approver->id,
                    'comment' => $request->decision_comment,
                ],
                user: $approver,
                description: "Refund {$saleReturn->refund_no} rejected by {$approver->username}"
            );
        }
    }
}
