<?php

namespace App\Actions\Inventory;

use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\StorageLocation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Move stock between storage locations.
 * Creates two ledger rows (out and in), transactionally, audited.
 */
class MoveStock
{
    /**
     * @throws ValidationException
     */
    public function execute(
        Batch $batch,
        int $fromLocationId,
        int $toLocationId,
        int $quantity,
        User $user,
        string $status = 'sellable',
        ?string $reason = null,
        ?User $secondPerson = null
    ): array {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('locations.quantity_must_be_positive', [
                    'default' => 'Quantity moved must be greater than zero.',
                ]),
            ]);
        }

        if ($fromLocationId === $toLocationId) {
            throw ValidationException::withMessages([
                'to_location_id' => __('locations.source_and_destination_must_differ', [
                    'default' => 'Source and destination locations must be different.',
                ]),
            ]);
        }

        $fromLoc = StorageLocation::findOrFail($fromLocationId);
        $toLoc = StorageLocation::findOrFail($toLocationId);

        if (! $fromLoc->is_active) {
            throw ValidationException::withMessages([
                'from_location_id' => __('locations.source_location_inactive', [
                    'default' => 'Source location is inactive.',
                ]),
            ]);
        }

        if (! $toLoc->is_active) {
            throw ValidationException::withMessages([
                'to_location_id' => __('locations.destination_location_inactive', [
                    'default' => 'Destination location is inactive.',
                ]),
            ]);
        }

        $isControlled = $batch->product->classification === 'controlled';
        if ($isControlled && $status === 'sellable' && $toLoc->type !== StorageLocation::TYPE_CONTROLLED_CABINET) {
            throw ValidationException::withMessages([
                'to_location_id' => __('controlled_drugs.controlled_cabinet_location_required', [
                    'default' => 'Controlled drug products must be stored in a controlled-cabinet location.',
                ]),
            ]);
        }

        $availableStock = (int) BatchStock::where('batch_id', $batch->id)
            ->where('location_id', $fromLocationId)
            ->where('status', $status)
            ->value('quantity');

        if ($availableStock < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('catalogue.insufficient_stock', [
                    'available' => $availableStock,
                    'requested' => $quantity,
                ]),
            ]);
        }

        return DB::transaction(function () use (
            $batch, $fromLoc, $toLoc, $quantity, $user, $status, $reason, $secondPerson
        ) {
            // 1. Move OUT from source location
            $outMovement = StockMovementService::post(
                batch: $batch,
                signedQuantity: -$quantity,
                type: 'transfer',
                source: $batch,
                user: $user,
                status: $status,
                locationId: $fromLoc->id,
                description: "Transfer to {$toLoc->name} ({$toLoc->code})".($reason ? ": {$reason}" : ''),
                secondPerson: $secondPerson
            );

            // 2. Move IN to destination location
            $inMovement = StockMovementService::post(
                batch: $batch,
                signedQuantity: $quantity,
                type: 'transfer',
                source: $batch,
                user: $user,
                status: $status,
                locationId: $toLoc->id,
                description: "Transfer from {$fromLoc->name} ({$fromLoc->code})".($reason ? ": {$reason}" : ''),
                secondPerson: $secondPerson
            );

            // Audit state change
            AuditLogger::record(
                action: 'stock.location.moved',
                subject: $batch,
                old: [
                    'location_id' => $fromLoc->id,
                    'location_code' => $fromLoc->code,
                ],
                new: [
                    'location_id' => $toLoc->id,
                    'location_code' => $toLoc->code,
                ],
                metadata: [
                    'product_id' => $batch->product_id,
                    'batch_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'from_location' => $fromLoc->name,
                    'to_location' => $toLoc->name,
                    'quantity' => $quantity,
                    'status' => $status,
                    'reason' => $reason,
                ],
                user: $user,
                description: "Moved {$quantity} units of batch {$batch->batch_no} from {$fromLoc->name} to {$toLoc->name}"
            );

            return [
                'out_movement' => $outMovement,
                'in_movement' => $inMovement,
            ];
        });
    }
}
