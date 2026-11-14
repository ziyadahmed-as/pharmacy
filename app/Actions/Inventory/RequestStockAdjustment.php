<?php

namespace App\Actions\Inventory;

use App\Models\AdjustmentRequest;
use App\Models\Batch;
use App\Models\User;
use App\Services\ApprovalService;
use App\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action to initiate a stock adjustment request (UC-06).
 *
 * @uc UC-06
 *
 * @fr FR-2.8
 *
 * @br BR-07, BR-14
 *
 * @ac AC-08
 */
class RequestStockAdjustment
{
    public function __construct(
        private readonly ApprovalService $approvalService
    ) {}

    /**
     * @param  array{
     *     batch_id: int,
     *     reason_type: string,
     *     quantity: int,
     *     written_reason: string,
     *     second_person_id?: int|null
     * }  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $user, array $data): AdjustmentRequest
    {
        if ($user->isSystemAdmin() || ! $user->hasPermission('stock.adjust.request')) {
            throw new AuthorizationException(__('catalogue.unauthorized_stock_adjustment'));
        }

        $batchId = $data['batch_id'] ?? null;
        /** @var Batch $batch */
        $batch = Batch::with(['product', 'batchStocks'])->findOrFail($batchId);

        $reasonType = $data['reason_type'] ?? '';
        $allowedReasons = [
            AdjustmentRequest::REASON_DAMAGE,
            AdjustmentRequest::REASON_LOSS,
            AdjustmentRequest::REASON_EXPIRY_WRITE_OFF,
            AdjustmentRequest::REASON_FOUND_STOCK,
        ];

        if (! in_array($reasonType, $allowedReasons, true)) {
            throw ValidationException::withMessages([
                'reason_type' => __('catalogue.invalid_adjustment_reason_type'),
            ]);
        }

        $writtenReason = trim((string) ($data['written_reason'] ?? ''));
        if ($writtenReason === '') {
            throw ValidationException::withMessages([
                'written_reason' => __('catalogue.written_reason_required'),
            ]);
        }

        $rawQty = (int) ($data['quantity'] ?? 0);
        if ($rawQty === 0) {
            throw ValidationException::withMessages([
                'quantity' => __('catalogue.adjustment_quantity_must_be_non_zero'),
            ]);
        }

        // Calculate signed quantity
        // Found stock increases inventory (+), all other reasons decrease (-)
        $signedQuantity = $reasonType === AdjustmentRequest::REASON_FOUND_STOCK
            ? abs($rawQty)
            : -abs($rawQty);

        // E2: Negative stock check
        if ($signedQuantity < 0) {
            $currentSellable = $batch->sellableStockQuantity();
            if ($currentSellable + $signedQuantity < 0) {
                throw ValidationException::withMessages([
                    'quantity' => __('catalogue.adjustment_cannot_make_stock_negative'),
                ]);
            }
        }

        // STEP 5: Controlled drug check
        $secondPersonId = $data['second_person_id'] ?? null;
        $isControlled = $batch->product->classification === 'controlled';
        $handlesControlled = (bool) setting('pharmacy.handles_controlled_drugs', true);

        if ($isControlled && $handlesControlled) {
            if (! $secondPersonId) {
                throw ValidationException::withMessages([
                    'second_person_id' => __('controlled_drugs.second_person_required'),
                ]);
            }

            if ((int) $secondPersonId === $user->id) {
                throw ValidationException::withMessages([
                    'second_person_id' => __('controlled_drugs.second_person_cannot_be_same'),
                ]);
            }

            $secondPerson = User::find($secondPersonId);
            if (! $secondPerson || $secondPerson->isSystemAdmin()) {
                throw ValidationException::withMessages([
                    'second_person_id' => __('controlled_drugs.second_person_invalid'),
                ]);
            }
        }

        return DB::transaction(function () use ($user, $batch, $reasonType, $signedQuantity, $writtenReason, $secondPersonId) {
            // 1. Create Adjustment Request record
            $adj = AdjustmentRequest::create([
                'batch_id' => $batch->id,
                'reason_type' => $reasonType,
                'quantity' => $signedQuantity,
                'written_reason' => $writtenReason,
                'status' => AdjustmentRequest::STATUS_PENDING_APPROVAL,
                'requested_by' => $user->id,
                'second_person_id' => $secondPersonId,
                'recount_done' => false,
            ]);

            // 2. Register approval request in UC-34 engine
            $approvalSummary = sprintf(
                'Stock adjustment (%s) of %d units for %s (Batch %s)',
                $reasonType,
                $signedQuantity,
                $batch->product->brand,
                $batch->batch_no
            );

            $approval = $this->approvalService->request(
                type: 'stock.adjustment',
                initiator: $user,
                payload: [
                    'adjustment_request_id' => $adj->id,
                    'batch_id' => $batch->id,
                    'quantity' => $signedQuantity,
                    'reason_type' => $reasonType,
                    'second_person_id' => $secondPersonId,
                ],
                summary: $approvalSummary,
                subject: $adj,
                comment: $writtenReason,
            );

            $adj->update(['approval_request_id' => $approval->id]);

            AuditLogger::record(
                action: 'stock.adjustment.requested',
                subject: $adj,
                old: [],
                new: [
                    'batch_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'quantity' => $signedQuantity,
                    'reason_type' => $reasonType,
                    'approval_request_id' => $approval->id,
                ],
                metadata: ['written_reason' => $writtenReason],
                user: $user,
                description: "Stock adjustment requested for batch {$batch->batch_no} ({$signedQuantity} units)"
            );

            return $adj;
        });
    }
}
