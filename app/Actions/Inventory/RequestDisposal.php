<?php

namespace App\Actions\Inventory;

use App\Models\Disposal;
use App\Models\QuarantineRecord;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action to request stock disposal (UC-09).
 *
 * Preconditions:
 * - Only quarantined stock may be disposed (STEP 2).
 * - For controlled drugs, record licensed performing person AND an independent named witness who is a different person (A1, E2).
 * - Quantity cannot exceed available batch/quarantine quantity (AC-25).
 *
 * @uc UC-09
 *
 * @fr FR-2.16, FR-2.17, FR-2.18
 *
 * @br BR-07, BR-11
 *
 * @ac AC-10, AC-25
 */
class RequestDisposal
{
    public function __construct(
        private readonly ApprovalService $approvalService
    ) {}

    /**
     * @param  array{
     *     quarantine_record_id: int,
     *     quantity: int,
     *     reason: string,
     *     method: string,
     *     supplier_name?: string|null,
     *     collector_name?: string|null,
     *     performer_id?: int|null,
     *     witness_id?: int|null,
     *     notes?: string|null,
     *     evidence_path?: string|null
     * }  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $user, array $data): Disposal
    {
        if ($user->isSystemAdmin() || ! $user->hasPermission('stock.dispose')) {
            throw new AuthorizationException(__('disposal.unauthorized'));
        }

        $quarantineRecordId = $data['quarantine_record_id'] ?? null;
        /** @var QuarantineRecord $quarantineRecord */
        $quarantineRecord = QuarantineRecord::with(['batch.product'])->find($quarantineRecordId);

        if (! $quarantineRecord) {
            throw ValidationException::withMessages([
                'quarantine_record_id' => __('disposal.quarantine_record_required'),
            ]);
        }

        // Precondition: only quarantined stock may be disposed
        if (! $quarantineRecord->isOpen()) {
            throw ValidationException::withMessages([
                'quarantine_record_id' => __('disposal.only_quarantined_stock_can_be_disposed'),
            ]);
        }

        $quantity = (int) ($data['quantity'] ?? 0);
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('disposal.quantity_positive'),
            ]);
        }

        $availableInRecord = $quarantineRecord->disposableQuantity();
        if ($quantity > $availableInRecord) {
            throw ValidationException::withMessages([
                'quantity' => __('disposal.quantity_exceeds_quarantine_record', [
                    'available' => $availableInRecord,
                ]),
            ]);
        }

        $batch = $quarantineRecord->batch;
        $batchQuarantineStock = $batch->quarantineStockQuantity();
        if ($quantity > $batchQuarantineStock) {
            throw ValidationException::withMessages([
                'quantity' => __('disposal.quantity_exceeds_batch_stock', [
                    'available' => $batchQuarantineStock,
                ]),
            ]);
        }

        $reason = $data['reason'] ?? '';
        if (! in_array($reason, Disposal::REASONS, true)) {
            throw ValidationException::withMessages([
                'reason' => __('disposal.invalid_reason'),
            ]);
        }

        $method = $data['method'] ?? '';
        if (! in_array($method, Disposal::METHODS, true)) {
            throw ValidationException::withMessages([
                'method' => __('disposal.invalid_method'),
            ]);
        }

        $supplierName = trim((string) ($data['supplier_name'] ?? ''));
        if ($method === Disposal::METHOD_RETURN_TO_SUPPLIER && $supplierName === '') {
            $supplierName = $batch->supplier_name ?: '';
            if ($supplierName === '') {
                throw ValidationException::withMessages([
                    'supplier_name' => __('disposal.supplier_name_required'),
                ]);
            }
        }

        $performerId = ! empty($data['performer_id']) ? (int) $data['performer_id'] : $user->id;
        $witnessId = ! empty($data['witness_id']) ? (int) $data['witness_id'] : null;

        // Controlled drug checks (A1, E2, AC-10)
        $isControlled = $batch->product->classification === 'controlled';
        $handlesControlled = (bool) setting('pharmacy.handles_controlled_drugs', true);

        if ($isControlled && $handlesControlled) {
            if (! $witnessId) {
                throw ValidationException::withMessages([
                    'witness_id' => __('disposal.witness_required_for_controlled'),
                ]);
            }

            if ($performerId === $witnessId) {
                throw ValidationException::withMessages([
                    'witness_id' => __('disposal.witness_cannot_be_performer'),
                ]);
            }

            // Performer must exist
            $performer = User::find($performerId);
            if (! $performer || $performer->isSystemAdmin()) {
                throw ValidationException::withMessages([
                    'performer_id' => __('disposal.invalid_performer'),
                ]);
            }

            // Witness must exist
            $witness = User::find($witnessId);
            if (! $witness || $witness->isSystemAdmin()) {
                throw ValidationException::withMessages([
                    'witness_id' => __('disposal.invalid_witness'),
                ]);
            }
        }

        $notes = ! empty($data['notes']) ? trim((string) $data['notes']) : null;
        $collectorName = ! empty($data['collector_name']) ? trim((string) $data['collector_name']) : null;
        $evidencePath = $data['evidence_path'] ?? null;

        return DB::transaction(function () use (
            $user,
            $batch,
            $quarantineRecord,
            $quantity,
            $reason,
            $method,
            $supplierName,
            $collectorName,
            $performerId,
            $witnessId,
            $notes,
            $evidencePath
        ) {
            $disposal = Disposal::create([
                'batch_id' => $batch->id,
                'quarantine_record_id' => $quarantineRecord->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'method' => $method,
                'supplier_name' => $supplierName ?: null,
                'collector_name' => $collectorName,
                'status' => Disposal::STATUS_PENDING,
                'requested_by' => $user->id,
                'performer_id' => $performerId,
                'witness_id' => $witnessId,
                'evidence_path' => $evidencePath,
                'notes' => $notes,
            ]);

            $summary = sprintf(
                'Stock disposal (%s - %s) of %d units for %s (Batch %s)',
                $method,
                $reason,
                $quantity,
                $batch->product->brand,
                $batch->batch_no
            );

            $approval = $this->approvalService->request(
                type: 'stock.disposal',
                initiator: $user,
                payload: [
                    'disposal_id' => $disposal->id,
                    'batch_id' => $batch->id,
                    'quarantine_record_id' => $quarantineRecord->id,
                    'quantity' => $quantity,
                    'method' => $method,
                    'reason' => $reason,
                    'performer_id' => $performerId,
                    'witness_id' => $witnessId,
                ],
                summary: $summary,
                subject: $disposal,
                comment: $notes,
            );

            $disposal->update(['approval_request_id' => $approval->id]);

            AuditLogger::record(
                action: 'stock.disposal.requested',
                subject: $disposal,
                old: [],
                new: [
                    'batch_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'quantity' => $quantity,
                    'reason' => $reason,
                    'method' => $method,
                    'approval_request_id' => $approval->id,
                ],
                metadata: [
                    'supplier_name' => $supplierName,
                    'performer_id' => $performerId,
                    'witness_id' => $witnessId,
                ],
                user: $user,
                description: "Stock disposal requested for batch {$batch->batch_no} ({$quantity} units)"
            );

            return $disposal;
        });
    }
}
