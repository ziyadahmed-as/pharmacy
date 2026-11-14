<?php

namespace App\Actions\Inventory;

use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\QuarantineRecord;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Action to quarantine stock from sellable to quarantine status.
 *
 * @uc UC-08 @fr FR-2.15, 2.5 @br BR-01, BR-10 @ac AC-07
 */
class QuarantineStock
{
    /**
     * @throws ValidationException
     */
    public function execute(
        Batch $batch,
        int $quantity,
        string $reason,
        ?string $note = null,
        ?User $actor = null,
        ?User $secondPerson = null
    ): QuarantineRecord {
        $user = $actor ?? Auth::user();

        if (! $user || ! $user->canAccessBusinessData()) {
            throw ValidationException::withMessages([
                'quarantine' => __('catalogue.unauthorized_quarantine'),
            ]);
        }

        if (trim($reason) === '' || ! in_array($reason, QuarantineRecord::REASONS, true)) {
            throw ValidationException::withMessages([
                'reason' => __('catalogue.quarantine_reason_required'),
            ]);
        }

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => __('catalogue.quarantine_quantity_positive'),
            ]);
        }

        $sellableStock = (int) BatchStock::where('batch_id', $batch->id)
            ->where('status', 'sellable')
            ->sum('quantity');

        if ($sellableStock < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('catalogue.insufficient_sellable_stock_for_quarantine', [
                    'available' => $sellableStock,
                    'requested' => $quantity,
                ]),
            ]);
        }

        return DB::transaction(function () use ($batch, $quantity, $reason, $note, $user, $secondPerson) {
            $record = QuarantineRecord::create([
                'batch_id' => $batch->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'status' => QuarantineRecord::STATUS_OPEN,
                'created_by' => $user->id,
                'note' => $note,
            ]);

            // 1) Deduct from sellable
            StockMovementService::post(
                batch: $batch,
                signedQuantity: -$quantity,
                type: 'quarantine',
                source: $record,
                user: $user,
                status: 'sellable',
                description: "Quarantined: {$reason}",
                secondPerson: $secondPerson
            );

            // 2) Add to quarantine
            StockMovementService::post(
                batch: $batch,
                signedQuantity: $quantity,
                type: 'quarantine',
                source: $record,
                user: $user,
                status: 'quarantine',
                description: "Quarantined: {$reason}",
                secondPerson: $secondPerson
            );

            AuditLogger::record(
                'stock.quarantine.created',
                $record,
                [],
                [],
                [
                    'batch_id' => $batch->id,
                    'batch_no' => $batch->batch_no,
                    'quantity' => $quantity,
                    'reason' => $reason,
                    'note' => $note,
                ],
                $user
            );

            return $record;
        });
    }
}
