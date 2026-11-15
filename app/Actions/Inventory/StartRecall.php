<?php

namespace App\Actions\Inventory;

use App\Enums\Role;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\QuarantineRecord;
use App\Models\Recall;
use App\Models\RecallBatch;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentSequence;
use App\Services\Notifier;
use App\Services\StockMovementService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action to initiate a product and batch recall (UC-10 / BRD FR-2.11, FR-2.12).
 *
 * STEP 1: Select product and batches, quarantine all sellable stock through StockMovementService,
 * block sale immediately, notify owner and manager.
 */
class StartRecall
{
    /**
     * @param  array<int, int>  $batchIds
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(
        User $user,
        int $productId,
        array $batchIds,
        string $reason,
        string|Carbon $recallDate,
        string $severity = Recall::SEVERITY_CLASS_II,
        ?string $sourceReference = null
    ): Recall {
        if ($user->isSystemAdmin() || ! $user->canAccessBusinessData()) {
            throw new AuthorizationException(__('recalls.unauthorized'));
        }

        if (! $user->hasPermission('stock.dispose') && ! $user->hasPermission('catalogue.manage')) {
            throw new AuthorizationException(__('recalls.unauthorized'));
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => __('recalls.reason_required'),
            ]);
        }

        if (empty($batchIds)) {
            throw ValidationException::withMessages([
                'batch_ids' => __('recalls.batches_required'),
            ]);
        }

        $batches = Batch::with('product')
            ->where('product_id', $productId)
            ->whereIn('id', $batchIds)
            ->get();

        if ($batches->isEmpty()) {
            throw ValidationException::withMessages([
                'batch_ids' => __('recalls.no_valid_batches_found'),
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $productId,
            $batches,
            $reason,
            $recallDate,
            $severity,
            $sourceReference
        ) {
            $recallNo = DocumentSequence::next('recall', 'RCL');

            $recall = Recall::create([
                'recall_no' => $recallNo,
                'product_id' => $productId,
                'source_reference' => $sourceReference,
                'reason' => $reason,
                'recall_date' => $recallDate instanceof Carbon ? $recallDate->toDateString() : $recallDate,
                'severity' => $severity,
                'status' => Recall::STATUS_OPEN,
                'created_by' => $user->id,
            ]);

            foreach ($batches as $batch) {
                // Find all sellable stocks for this batch across storage locations
                $sellableStocks = BatchStock::where('batch_id', $batch->id)
                    ->where('status', 'sellable')
                    ->where('quantity', '>', 0)
                    ->get();

                $totalSellable = (int) $sellableStocks->sum('quantity');

                if ($totalSellable > 0) {
                    // Create QuarantineRecord
                    $quarantineRecord = QuarantineRecord::create([
                        'batch_id' => $batch->id,
                        'quantity' => $totalSellable,
                        'reason' => QuarantineRecord::REASON_RECALLED,
                        'status' => QuarantineRecord::STATUS_OPEN,
                        'created_by' => $user->id,
                        'note' => "Recall {$recall->recall_no}: {$reason}",
                    ]);

                    // Move stock from sellable to quarantine for each location
                    foreach ($sellableStocks as $stockRow) {
                        $qty = $stockRow->quantity;
                        if ($qty <= 0) {
                            continue;
                        }

                        // Deduct sellable
                        StockMovementService::post(
                            batch: $batch,
                            signedQuantity: -$qty,
                            type: 'quarantine',
                            source: $quarantineRecord,
                            user: $user,
                            status: 'sellable',
                            locationId: $stockRow->location_id,
                            description: "Quarantined by Recall {$recall->recall_no}: {$reason}"
                        );

                        // Add quarantine
                        StockMovementService::post(
                            batch: $batch,
                            signedQuantity: $qty,
                            type: 'quarantine',
                            source: $quarantineRecord,
                            user: $user,
                            status: 'quarantine',
                            locationId: $stockRow->location_id,
                            description: "Quarantined by Recall {$recall->recall_no}: {$reason}"
                        );
                    }
                } else {
                    // Even if 0 sellable stock is currently on hand, create a QuarantineRecord
                    // so legacy sellability guards & quarantine listings reflect the recalled status
                    QuarantineRecord::create([
                        'batch_id' => $batch->id,
                        'quantity' => 0,
                        'reason' => QuarantineRecord::REASON_RECALLED,
                        'status' => QuarantineRecord::STATUS_OPEN,
                        'created_by' => $user->id,
                        'note' => "Recall {$recall->recall_no}: {$reason} (0 on hand)",
                    ]);
                }

                // Create RecallBatch entry
                RecallBatch::create([
                    'recall_id' => $recall->id,
                    'batch_id' => $batch->id,
                    'quantity_found' => $totalSellable,
                    'quantity_quarantined' => $totalSellable,
                    'action' => 'quarantined',
                ]);
            }

            AuditLogger::record(
                action: 'stock.recall.started',
                subject: $recall,
                old: [],
                new: [
                    'recall_no' => $recall->recall_no,
                    'product_id' => $productId,
                    'severity' => $severity,
                    'batches_count' => $batches->count(),
                ],
                metadata: [
                    'recall_no' => $recall->recall_no,
                    'reason' => $reason,
                    'source_reference' => $sourceReference,
                    'batch_ids' => $batches->pluck('id')->all(),
                ],
                user: $user,
                description: "Recall {$recall->recall_no} started for product ID {$productId}"
            );

            // Notify Owner and Pharmacy Manager
            Notifier::toRoles(
                [Role::Owner, Role::PharmacyManager],
                'stock.recall.started',
                [
                    'message_key' => 'recalls.notify.started',
                    'message_params' => [
                        'recall_no' => $recall->recall_no,
                        'product' => $batches->first()->product->brand,
                    ],
                    'recall_id' => $recall->id,
                ],
                exceptUserId: $user->id
            );

            return $recall->load(['product', 'batches.batch']);
        });
    }
}
