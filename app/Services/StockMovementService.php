<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Sale;
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
        ?string $description = null,
        ?User $secondPerson = null,
        ?string $patientName = null,
        ?string $prescriberName = null
    ): StockMovement {
        return DB::transaction(function () use ($batch, $signedQuantity, $type, $source, $user, $status, $location, $description, $secondPerson, $patientName, $prescriberName) {
            $isControlled = $batch->product->classification === 'controlled';
            $handlesControlled = (bool) setting('pharmacy.handles_controlled_drugs', true);

            // E2: non-dispensing movement of a controlled drug requires a second named person
            if ($isControlled && $handlesControlled && ! in_array($type, ['sale', 'receipt'], true)) {
                if (! $secondPerson) {
                    throw new RuntimeException(__('controlled_drugs.second_person_required'));
                }

                if ($secondPerson->id === $user->id) {
                    throw new RuntimeException(__('controlled_drugs.second_person_cannot_be_same'));
                }
            }

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

            $movement = StockMovement::create([
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

            // Controlled drug register integration (BR-11, FR-4.4)
            if ($isControlled && $handlesControlled && ($status === 'sellable' || $type === 'disposal')) {
                $entryType = match ($type) {
                    'sale' => 'dispense',
                    'receipt' => 'receipt',
                    'disposal' => 'disposal',
                    default => 'adjustment',
                };

                $actualPatient = $patientName;
                $actualPrescriber = $prescriberName;
                $sale = null;

                if ($source instanceof Sale) {
                    $sale = $source;
                    if (! $actualPatient && $source->prescription) {
                        $actualPatient = $source->prescription->customer?->name;
                    }
                    if (! $actualPrescriber && $source->prescription) {
                        $actualPrescriber = $source->prescription->prescriber_name;
                    }
                }

                ControlledDrugRegister::record(
                    product: $batch->product,
                    batch: $batch,
                    entryType: $entryType,
                    quantity: abs($signedQuantity),
                    runningBalance: $newBalance,
                    user: $user,
                    secondPerson: $secondPerson,
                    patientName: $actualPatient,
                    prescriberName: $actualPrescriber,
                    sale: $sale,
                    stockMovement: $movement,
                    notes: $description
                );
            }

            return $movement;
        });
    }
}
