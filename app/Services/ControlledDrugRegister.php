<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\ControlledDrugRegister as ControlledDrugRegisterEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use RuntimeException;

/**
 * Service to record controlled drug register entries.
 * Enforces atomic ledger alignment, running balance validation, and dual-authorization rules.
 *
 * @uc UC-22
 *
 * @fr FR-4.4, FR-4.3, FR-4.9
 *
 * @br BR-03, BR-11, BR-12
 *
 * @ac AC-13, AC-34
 */
class ControlledDrugRegister
{
    /**
     * Record an entry into the controlled drug register.
     *
     * @throws RuntimeException
     */
    public static function record(
        Product $product,
        Batch $batch,
        string $entryType,
        int $quantity,
        int $runningBalance,
        User $user,
        ?User $secondPerson = null,
        ?string $patientName = null,
        ?string $prescriberName = null,
        ?Sale $sale = null,
        ?StockMovement $stockMovement = null,
        ?string $notes = null
    ): ControlledDrugRegisterEntry {
        // Feature switch check (OQ-01)
        if (! (bool) setting('pharmacy.handles_controlled_drugs', true)) {
            throw new RuntimeException(__('controlled_drugs.module_disabled'));
        }

        // Verify product is controlled
        if ($product->classification !== 'controlled') {
            throw new RuntimeException(__('controlled_drugs.not_controlled_product', ['product' => $product->brand]));
        }

        // Verify batch belongs to product
        if ($batch->product_id !== $product->id) {
            throw new RuntimeException(__('controlled_drugs.batch_mismatch'));
        }

        // Verify running balance equals stock ledger (StockMovement balance_after)
        if ($stockMovement && $runningBalance !== $stockMovement->balance_after) {
            throw new RuntimeException(__('controlled_drugs.running_balance_mismatch', [
                'register' => $runningBalance,
                'ledger' => $stockMovement->balance_after,
            ]));
        }

        // E2: non-dispensing & non-receipt movements require a second named person
        if (! in_array($entryType, ['dispense', 'receipt'], true)) {
            if (! $secondPerson) {
                throw new RuntimeException(__('controlled_drugs.second_person_required'));
            }

            if ($secondPerson->id === $user->id) {
                throw new RuntimeException(__('controlled_drugs.second_person_cannot_be_same'));
            }
        }

        return ControlledDrugRegisterEntry::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'entry_type' => $entryType,
            'quantity' => $quantity,
            'running_balance' => $runningBalance,
            'patient_name' => $patientName,
            'prescriber_name' => $prescriberName,
            'user_id' => $user->id,
            'second_person_id' => $secondPerson?->id,
            'sale_id' => $sale?->id,
            'stock_movement_id' => $stockMovement?->id,
            'notes' => $notes,
        ]);
    }
}
