<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The ONLY writer of `batch_stocks` and `stock_movements`.
 * Enforces atomic transactions, non-negative stock balance, and ledger accountability.
 *
 * @uc UC-04 @br BR-04, BR-08
 */
class StockMovementService
{
    /**
     * Post a movement against a batch stock.
     *
     * @param  int  $signedQuantity  Positive for addition (receipt/adjustment up), negative for deduction (sale/adjustment down)
     * @param  string  $type  Movement type: receipt, sale, quarantine, adjustment, return
     * @param  Model  $source  The originating model (e.g. StockReceipt, Sale)
     * @param  string  $status  Stock status: 'sellable' or 'quarantine'
     *
     * @throws RuntimeException
     */
    public static function post(
        Batch $batch,
        int $signedQuantity,
        string $type,
        Model $source,
        User $user,
        string $status = 'sellable',
        ?string $location = null,
        ?string $description = null
    ): StockMovement {
        return DB::transaction(function () use ($batch, $signedQuantity, $type, $source, $user, $status, $location, $description) {
            $stock = BatchStock::where('batch_id', $batch->id)
                ->where('status', $status)
                ->when($location, fn ($q) => $q->where('location', $location), fn ($q) => $q->whereNull('location'))
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                if ($signedQuantity < 0) {
                    throw new RuntimeException(__('catalogue.negative_stock_refused'));
                }

                $stock = BatchStock::create([
                    'batch_id' => $batch->id,
                    'status' => $status,
                    'location' => $location,
                    'quantity' => 0,
                ]);
            }

            $newBalance = $stock->quantity + $signedQuantity;

            if ($newBalance < 0) {
                throw new RuntimeException(__('catalogue.insufficient_stock', [
                    'available' => $stock->quantity,
                    'requested' => abs($signedQuantity),
                ]));
            }

            $stock->quantity = $newBalance;
            $stock->save();

            return StockMovement::create([
                'batch_id' => $batch->id,
                'type' => $type,
                'quantity' => $signedQuantity,
                'balance_after' => $newBalance,
                'stock_status' => $status,
                'source_type' => get_class($source),
                'source_id' => $source->getKey(),
                'user_id' => $user->id,
                'description' => $description,
                'created_at' => now(),
            ]);
        });
    }
}
