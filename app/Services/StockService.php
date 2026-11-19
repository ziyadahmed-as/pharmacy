<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * THE only writer of stock quantities (BR-04, BR-08).
 *
 * Every quantity change must pass through applyMovement().
 * No other code may write batches.quantity_on_hand or batches.quarantine_qty directly.
 *
 * In one database transaction:
 *  1. Lock the batch row (SELECT FOR UPDATE) — concurrent safety.
 *  2. Refuse any change that would make quantity_on_hand or quarantine_qty negative.
 *  3. Update the relevant quantity column on batches.
 *  4. Transition batch.status: DEPLETED when quantity_on_hand → 0; SELLABLE on restock.
 *  5. Keep batch_stocks in sync for per-location accounting.
 *  6. Write one append-only StockMovement ledger row with balance_after.
 *  7. Optionally write to the controlled-drug register.
 *  8. Idempotency: if a row with matching (batch_id, type, source_type, source_id)
 *     already exists, return it immediately without double-posting.
 *
 * Movement types / direction (from StockMovement::TYPE_* constants):
 *   receipt            + sellable
 *   sale               - sellable
 *   sale_void          + sellable
 *   return_from_customer  + sellable | + quarantine (non-resaleable)
 *   adjustment         +/- sellable (signed quantity, approved difference)
 *   stocktake_variance +/- sellable (signed quantity)
 *   quarantine         - sellable (transfer out)  / + quarantine (transfer in)
 *   quarantine_release + sellable / - quarantine (reversal; never for expired)
 *   disposal           - quarantine
 *   supplier_return    - quarantine
 *
 * @br BR-04, BR-08
 */
final class StockService
{
    /**
     * Apply a signed quantity movement against a batch.
     *
     * @param  Batch   $batch
     * @param  int     $signedQuantity  positive = add, negative = remove
     * @param  string  $type            StockMovement::TYPE_* constant
     * @param  Model   $source          The originating document (Sale, StockReceipt, …)
     * @param  User    $user
     * @param  string  $stockStatus     'sellable' | 'quarantine'
     * @param  int|string|null $locationId  Optional storage location
     * @param  string|null     $description
     * @param  User|null       $secondPerson  Controlled-drug witness
     * @param  string|null     $patientName
     * @param  string|null     $prescriberName
     * @param  string|null     $idempotencyKey  Optional; if supplied, deduplicates retries
     *
     * @throws RuntimeException  When the movement would make qty negative
     */
    public static function applyMovement(
        Batch $batch,
        int $signedQuantity,
        string $type,
        Model $source,
        User $user,
        string $stockStatus = 'sellable',
        int|string|null $locationId = null,
        ?string $description = null,
        ?User $secondPerson = null,
        ?string $patientName = null,
        ?string $prescriberName = null,
        ?string $idempotencyKey = null
    ): StockMovement {
        return DB::transaction(function () use (
            $batch, $signedQuantity, $type, $source, $user,
            $stockStatus, $locationId, $description,
            $secondPerson, $patientName, $prescriberName, $idempotencyKey
        ) {
            // ----------------------------------------------------------------
            // 1. Idempotency check (before the lock to avoid unnecessary waits)
            // ----------------------------------------------------------------
            if ($idempotencyKey !== null) {
                $existing = StockMovement::where('batch_id', $batch->id)
                    ->where('type', $type)
                    ->where('stock_status', $stockStatus)
                    ->where('source_type', get_class($source))
                    ->where('source_id', $source->getKey())
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $isControlled    = $batch->product->classification === 'controlled';
            $handlesControlled = (bool) setting('pharmacy.handles_controlled_drugs', true);

            // E2: controlled drugs outside receipt/sale need a second witness
            if ($isControlled && $handlesControlled && ! in_array($type, ['sale', 'receipt'], true)) {
                if (! $secondPerson) {
                    throw new RuntimeException(__('controlled_drugs.second_person_required'));
                }
                if ($secondPerson->id === $user->id) {
                    throw new RuntimeException(__('controlled_drugs.second_person_cannot_be_same'));
                }
            }

            // ----------------------------------------------------------------
            // 2. Lock batch row for update (concurrency guard)
            // ----------------------------------------------------------------
            $lockedBatch = Batch::lockForUpdate()->findOrFail($batch->id);

            // ----------------------------------------------------------------
            // 3. Compute new quantity and refuse if it would go negative
            // ----------------------------------------------------------------
            if ($stockStatus === 'sellable') {
                $currentQty = (int) $lockedBatch->quantity_on_hand;
                $newQty     = $currentQty + $signedQuantity;
                if ($newQty < 0) {
                    throw new RuntimeException(__('catalogue.insufficient_stock', [
                        'available' => $currentQty,
                        'requested' => abs($signedQuantity),
                    ]));
                }
            } else {
                // quarantine
                $currentQty = (int) $lockedBatch->quarantine_qty;
                $newQty     = $currentQty + $signedQuantity;
                if ($newQty < 0) {
                    throw new RuntimeException(__('catalogue.insufficient_stock', [
                        'available' => $currentQty,
                        'requested' => abs($signedQuantity),
                    ]));
                }
            }

            // ----------------------------------------------------------------
            // 4. Update batch quantity columns (the single source of truth)
            // ----------------------------------------------------------------
            if ($stockStatus === 'sellable') {
                $lockedBatch->quantity_on_hand = $newQty;
            } else {
                $lockedBatch->quarantine_qty = $newQty;
            }

            // 4a. DEPLETED / SELLABLE transition driven here
            if ($stockStatus === 'sellable') {
                if ($newQty === 0 && $lockedBatch->status === Batch::STATUS_SELLABLE) {
                    $lockedBatch->status = Batch::STATUS_DEPLETED;
                } elseif ($newQty > 0 && $lockedBatch->status === Batch::STATUS_DEPLETED) {
                    $lockedBatch->status = Batch::STATUS_SELLABLE;
                }
            }

            // Save without firing the BatchStock.saved observer (we update it ourselves below)
            Batch::withoutEvents(fn () => $lockedBatch->save());

            // ----------------------------------------------------------------
            // 5. Keep batch_stocks in sync (per-location accounting)
            // ----------------------------------------------------------------
            $resolvedLocationId = self::resolveLocation($batch, $stockStatus, $locationId);

            $stockRow = BatchStock::where('batch_id', $batch->id)
                ->where('status', $stockStatus)
                ->when($resolvedLocationId,
                    fn ($q) => $q->where('location_id', $resolvedLocationId),
                    fn ($q) => $q->whereNull('location_id')
                )
                ->lockForUpdate()
                ->first();

            if (! $stockRow && $resolvedLocationId) {
                // Try to pick up an existing null-location row and assign it
                $stockRow = BatchStock::where('batch_id', $batch->id)
                    ->where('status', $stockStatus)
                    ->whereNull('location_id')
                    ->lockForUpdate()
                    ->first();
                if ($stockRow) {
                    $stockRow->location_id = $resolvedLocationId;
                }
            }

            if (! $stockRow) {
                if ($signedQuantity < 0) {
                    throw new RuntimeException(__('catalogue.negative_stock_refused'));
                }
                // Suppress BatchStock.saved observer to avoid duplicate status transitions
                BatchStock::withoutEvents(fn () => BatchStock::create([
                    'batch_id'    => $batch->id,
                    'status'      => $stockStatus,
                    'location_id' => $resolvedLocationId,
                    'quantity'    => 0,
                ]));
                $stockRow = BatchStock::where('batch_id', $batch->id)
                    ->where('status', $stockStatus)
                    ->when($resolvedLocationId,
                        fn ($q) => $q->where('location_id', $resolvedLocationId),
                        fn ($q) => $q->whereNull('location_id')
                    )
                    ->first();
            }

            $stockRow->quantity = max(0, $stockRow->quantity + $signedQuantity);
            BatchStock::withoutEvents(fn () => $stockRow->save());

            // ----------------------------------------------------------------
            // 6. Write the append-only ledger row
            // ----------------------------------------------------------------
            $movement = StockMovement::create([
                'batch_id'      => $batch->id,
                'product_id'    => $batch->product_id,
                'location_id'   => $resolvedLocationId,
                'type'          => $type,
                'quantity'      => $signedQuantity,
                'balance_after' => $newQty,
                'stock_status'  => $stockStatus,
                'source_type'   => get_class($source),
                'source_id'     => $source->getKey(),
                'user_id'       => $user->id,
                'description'   => $description,
                'created_at'    => now(),
            ]);

            // ----------------------------------------------------------------
            // 7. Controlled-drug register (optional)
            // ----------------------------------------------------------------
            if ($isControlled && $handlesControlled
                && ($stockStatus === 'sellable' || in_array($type, ['disposal', 'supplier_return'], true))) {
                $entryType = match ($type) {
                    'sale'                      => 'dispense',
                    'receipt'                   => 'receipt',
                    'disposal', 'supplier_return' => 'disposal',
                    default                     => 'adjustment',
                };

                $actualPatient   = $patientName;
                $actualPrescriber = $prescriberName;
                $prescriberId    = null;
                $saleModel       = null;

                if ($source instanceof Sale) {
                    $saleModel = $source;
                    if (! $actualPatient && $source->prescription) {
                        $actualPatient = $source->prescription->customer?->name;
                    }
                    if (! $actualPrescriber && $source->prescription) {
                        $actualPrescriber = $source->prescription->prescriber_name;
                    }
                    if ($source->prescription) {
                        $prescriberId = $source->prescription->prescriber_id;
                    }
                }

                ControlledDrugRegister::record(
                    product: $batch->product,
                    batch: $batch,
                    entryType: $entryType,
                    quantity: abs($signedQuantity),
                    runningBalance: $newQty,
                    user: $user,
                    secondPerson: $secondPerson,
                    patientName: $actualPatient,
                    prescriberName: $actualPrescriber,
                    sale: $saleModel,
                    stockMovement: $movement,
                    notes: $description,
                    prescriberId: $prescriberId
                );
            }

            return $movement;
        });
    }

    // -------------------------------------------------------------------------
    // Helper: resolve storage location
    // -------------------------------------------------------------------------

    private static function resolveLocation(Batch $batch, string $status, int|string|null $locationId): ?int
    {
        if (is_numeric($locationId)) {
            return (int) $locationId;
        }

        if (is_string($locationId) && trim($locationId) !== '') {
            $loc = StorageLocation::where('code', $locationId)
                ->orWhere('name', $locationId)
                ->first();
            return $loc?->id;
        }

        // Fall back to existing stock row location
        $existing = BatchStock::where('batch_id', $batch->id)
            ->where('status', $status)
            ->where('quantity', '>', 0)
            ->first()
            ?? BatchStock::where('batch_id', $batch->id)
                ->where('status', $status)
                ->first();

        if ($existing?->location_id) {
            return $existing->location_id;
        }

        $isControlled = $batch->product->classification === 'controlled';

        if ($isControlled) {
            return StorageLocation::getDefaultControlledCabinet()?->id;
        }
        if ($status === 'quarantine') {
            return StorageLocation::getDefaultQuarantine()?->id;
        }
        return StorageLocation::getDefaultDispensary()?->id;
    }
}
