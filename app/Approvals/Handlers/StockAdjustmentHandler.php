<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Enums\Role;
use App\Exceptions\ApprovalException;
use App\Models\AdjustmentRequest;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Handles approval decisions for stock adjustments (UC-06).
 *
 * On approval  : executes StockMovementService::post() (type adjustment),
 *                enforces recount requirement (BR-07), negative stock checks (E2),
 *                and second person for controlled drugs.
 * On rejection : leaves stock untouched and updates adjustment status (A1, E3).
 *
 * @uc UC-06 @fr FR-2.8 @br BR-07,BR-14 @ac AC-08
 */
class StockAdjustmentHandler implements ApprovalHandler
{
    public function type(): string
    {
        return 'stock.adjustment';
    }

    /**
     * Pharmacy staff initiates -> PM approves (Owner if PM raised it - A2).
     *
     * @param  array<string, mixed>  $payload
     * @return list<Role>
     */
    public function eligibleApproverRoles(User $initiator, array $payload): array
    {
        if ($initiator->hasRole(Role::PharmacyManager) || $initiator->hasRole(Role::Owner)) {
            return [Role::Owner];
        }

        return [Role::PharmacyManager, Role::Owner];
    }

    /**
     * Run inside the engine's transaction — throw to leave the request pending.
     */
    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $adjustmentRequestId = $request->payload['adjustment_request_id'] ?? null;

        /** @var AdjustmentRequest $adj */
        $adj = AdjustmentRequest::with(['batch.product', 'secondPerson'])->findOrFail($adjustmentRequestId);

        if ($adj->isApproved()) {
            return;
        }

        // BR-07: Recount required above threshold or if threshold is "Not set"
        if ($adj->requiresRecount() && ! $adj->recount_done) {
            throw new ApprovalException(__('catalogue.recount_required_before_approval'));
        }

        // E2: Negative stock refused
        $currentSellable = $adj->batch->sellableStockQuantity();
        if ($currentSellable + $adj->quantity < 0) {
            throw new RuntimeException(__('catalogue.negative_stock_refused'));
        }

        DB::transaction(function () use ($adj, $approver) {
            // 1. Post ledger movement (and controlled register entry if applicable)
            StockMovementService::post(
                batch: $adj->batch,
                signedQuantity: $adj->quantity,
                type: 'adjustment',
                source: $adj,
                user: $approver,
                status: 'sellable',
                description: sprintf(
                    'Stock adjustment (%s): %s',
                    $adj->reason_type,
                    $adj->written_reason
                ),
                secondPerson: $adj->secondPerson
            );

            // 2. Mark adjustment approved
            $adj->forceFill([
                'status' => AdjustmentRequest::STATUS_APPROVED,
            ])->save();

            // 3. Central immutable audit trail
            AuditLogger::record(
                action: 'stock.adjustment.approved',
                subject: $adj,
                old: ['status' => AdjustmentRequest::STATUS_PENDING_APPROVAL],
                new: ['status' => AdjustmentRequest::STATUS_APPROVED],
                metadata: [
                    'batch_id' => $adj->batch_id,
                    'batch_no' => $adj->batch->batch_no,
                    'quantity' => $adj->quantity,
                    'reason_type' => $adj->reason_type,
                    'recount_done' => $adj->recount_done,
                ],
                user: $approver,
                description: "Stock adjustment approved for batch {$adj->batch->batch_no} ({$adj->quantity} units)"
            );
        });
    }

    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        $adjustmentRequestId = $request->payload['adjustment_request_id'] ?? null;
        if (! $adjustmentRequestId) {
            return;
        }

        /** @var AdjustmentRequest|null $adj */
        $adj = AdjustmentRequest::find($adjustmentRequestId);
        if (! $adj) {
            return;
        }

        $newStatus = $request->isExpired()
            ? AdjustmentRequest::STATUS_EXPIRED
            : AdjustmentRequest::STATUS_REJECTED;

        $adj->forceFill(['status' => $newStatus])->save();

        AuditLogger::record(
            action: 'stock.adjustment.rejected',
            subject: $adj,
            old: ['status' => AdjustmentRequest::STATUS_PENDING_APPROVAL],
            new: ['status' => $newStatus],
            metadata: ['rejection_comment' => $request->decided_comment],
            user: $approver,
            description: "Stock adjustment request {$newStatus}"
        );
    }
}
