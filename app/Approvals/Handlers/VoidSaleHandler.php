<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Handles approval decisions for voiding a completed sale (UC-15).
 *
 * On approval  : marks sale voided, restores stock to the original batches,
 *                writes reversal ledger movements and audit entry.
 * On rejection : leaves the sale and stock untouched; requester sees comment.
 *
 * @uc UC-15 @fr FR-3.7 @br BR-05,BR-14 @ac AC-14
 */
class VoidSaleHandler implements ApprovalHandler
{
    public function type(): string
    {
        return 'sale.void';
    }

    /**
     * PM approves for staff; Owner approves when PM raised the request.
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
     * Run inside the engine's savepoint — throw to leave the request pending.
     */
    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $saleId = $request->payload['sale_id'] ?? null;

        /** @var Sale $sale */
        $sale = Sale::with(['lines.batch', 'payments'])->findOrFail($saleId);

        if ($sale->isVoided()) {
            throw new RuntimeException(__('sales.sale_already_voided'));
        }

        if (! $sale->isCompleted()) {
            throw new RuntimeException(__('sales.void_error_sale_not_completed'));
        }

        DB::transaction(function () use ($sale, $request, $approver) {
            // 1. Mark sale voided (keep all original rows — BR-05).
            $old = ['status' => $sale->status];
            $sale->forceFill(['status' => 'voided'])->save();

            // 2. Restore stock to the same batches (BR-14 — reversal to original batches).
            foreach ($sale->lines as $line) {
                if (! $line->batch) {
                    continue;
                }

                StockMovementService::post(
                    batch: $line->batch,
                    signedQuantity: $line->quantity,   // positive = returning to stock
                    type: 'sale_void',
                    source: $sale,
                    user: $approver,
                    status: 'sellable',
                    description: sprintf(
                        'Void of sale %s (line %d) approved by %s',
                        $sale->receipt_no,
                        $line->id,
                        $approver->name
                    )
                );
            }

            // 3. Audit — record both initiator and approver IDs (AC-14).
            $initiatorId = $request->initiator_id;
            AuditLogger::record(
                'sale.voided',
                $sale,
                $old,
                ['status' => 'voided'],
                [
                    'receipt_no' => $sale->receipt_no,
                    'reason' => $request->payload['reason'] ?? $request->initiator_comment,
                    'initiator_id' => $initiatorId,
                    'approver_id' => $approver->id,
                    'total' => $sale->total,
                ],
                $approver
            );
        });
    }

    /**
     * Rejection: leave everything as-is; the approval engine notifies the initiator.
     */
    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        // Nothing to undo — the sale was never touched.
    }
}
