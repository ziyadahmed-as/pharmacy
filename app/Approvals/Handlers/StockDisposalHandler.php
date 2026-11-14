<?php

namespace App\Approvals\Handlers;

use App\Approvals\ApprovalHandler;
use App\Enums\Role;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalRequest;
use App\Models\Disposal;
use App\Models\QuarantineRecord;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockMovementService;
use RuntimeException;

/**
 * Handles approval decisions for stock disposal and supplier return (UC-09).
 *
 * Preconditions:
 * - PM approves, Owner if the PM raised it (A2).
 * - Initiator cannot approve (E1 / AC-10).
 * - Refused for controlled drugs without an independent witness (A1, E2, AC-10).
 * - On approval: posts movement via StockMovementService, removes stock from quarantine,
 *   updates quarantine records, and generates disposal certificate (FR-2.19).
 * - On rejection: leaves stock in quarantine untouched (A2).
 *
 * @uc UC-09
 *
 * @fr FR-2.16, FR-2.17, FR-2.18, FR-2.19
 *
 * @br BR-07, BR-11
 *
 * @ac AC-10, AC-25
 */
class StockDisposalHandler implements ApprovalHandler
{
    public function type(): string
    {
        return 'stock.disposal';
    }

    /**
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

    public function onApproved(ApprovalRequest $request, User $approver): void
    {
        $disposalId = $request->payload['disposal_id'] ?? null;

        /** @var Disposal $disposal */
        $disposal = Disposal::with(['batch.product', 'quarantineRecord', 'performer', 'witness'])->findOrFail($disposalId);

        if ($disposal->isApproved()) {
            return;
        }

        $batch = $disposal->batch;
        $isControlled = $batch->product->classification === 'controlled';
        $handlesControlled = (bool) setting('pharmacy.handles_controlled_drugs', true);

        // Controlled drug rule (A1, E2, AC-10): independent witness is required
        if ($isControlled && $handlesControlled) {
            if (! $disposal->witness_id) {
                throw new ApprovalException(__('disposal.witness_required_for_controlled'));
            }

            if ($disposal->witness_id === $disposal->performer_id) {
                throw new ApprovalException(__('disposal.witness_cannot_be_performer'));
            }
        }

        // Verify quarantine stock availability
        $currentQuarantine = $batch->quarantineStockQuantity();
        if ($currentQuarantine < $disposal->quantity) {
            throw new RuntimeException(__('catalogue.insufficient_stock', [
                'available' => $currentQuarantine,
                'requested' => $disposal->quantity,
            ]));
        }

        // 1. Post movement through StockMovementService
        $movementType = $disposal->isReturnToSupplier() ? 'supplier_return' : 'disposal';
        $description = sprintf('Stock disposal (%s): %s', $disposal->method, $disposal->reason);
        if ($disposal->isReturnToSupplier() && $disposal->supplier_name) {
            $description .= ' - Supplier: '.$disposal->supplier_name;
        }
        if ($disposal->notes) {
            $description .= ' ('.$disposal->notes.')';
        }

        $performerUser = $disposal->performer ?: $approver;
        $witnessUser = $disposal->witness;

        StockMovementService::post(
            batch: $batch,
            signedQuantity: -$disposal->quantity,
            type: $movementType,
            source: $disposal,
            user: $performerUser,
            status: 'quarantine',
            description: $description,
            secondPerson: $witnessUser
        );

        // 2. Update quarantine record status if fully disposed
        $qr = $disposal->quarantineRecord;
        if ($qr && $qr->disposableQuantity() <= 0) {
            $qr->update([
                'status' => $disposal->isReturnToSupplier()
                    ? QuarantineRecord::STATUS_RETURNED
                    : QuarantineRecord::STATUS_DISPOSED,
            ]);
        }

        // 3. Generate certificate number and close disposal
        $certNo = Disposal::generateCertificateNo($disposal->id);
        $disposal->update([
            'status' => Disposal::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'certificate_no' => $certNo,
            'disposed_at' => now(),
        ]);

        // 4. Audit trail
        AuditLogger::record(
            action: 'stock.disposal.approved',
            subject: $disposal,
            old: ['status' => Disposal::STATUS_PENDING],
            new: [
                'status' => Disposal::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'certificate_no' => $certNo,
            ],
            metadata: [
                'batch_id' => $batch->id,
                'quantity' => $disposal->quantity,
                'method' => $disposal->method,
            ],
            user: $approver,
            description: "Stock disposal approved for batch {$batch->batch_no} ({$disposal->quantity} units, cert {$certNo})"
        );
    }

    public function onRejected(ApprovalRequest $request, User $approver): void
    {
        $disposalId = $request->payload['disposal_id'] ?? null;

        /** @var Disposal $disposal */
        $disposal = Disposal::findOrFail($disposalId);

        if ($disposal->isRejected()) {
            return;
        }

        $disposal->update([
            'status' => Disposal::STATUS_REJECTED,
            'rejected_by' => $approver->id,
            'rejection_reason' => $request->decision_comment,
        ]);

        AuditLogger::record(
            action: 'stock.disposal.rejected',
            subject: $disposal,
            old: ['status' => Disposal::STATUS_PENDING],
            new: [
                'status' => Disposal::STATUS_REJECTED,
                'rejected_by' => $approver->id,
                'rejection_reason' => $request->decision_comment,
            ],
            metadata: [
                'disposal_id' => $disposal->id,
                'reason' => $request->decision_comment,
            ],
            user: $approver,
            description: "Stock disposal rejected for batch {$disposal->batch->batch_no} (Request #{$disposal->id})"
        );
    }
}
