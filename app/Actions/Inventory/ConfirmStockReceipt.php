<?php

namespace App\Actions\Inventory;

use App\Models\Batch;
use App\Models\StockReceipt;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Confirms a stock receipt (BR-19, AC-05, AC-06).
 * All-or-nothing: good quantities go to sellable stock, damaged to quarantine, ledger written.
 *
 * @uc UC-04 @br BR-08, BR-19 @ac AC-05, AC-06
 */
class ConfirmStockReceipt
{
    /**
     * @param  bool  $reject  If true, marks as rejected without posting stock
     *
     * @throws ValidationException
     */
    public function execute(StockReceipt $receipt, ?User $user = null, bool $reject = false): StockReceipt
    {
        $actor = $user ?? Auth::user();

        if (! $actor || ! $actor->hasPermission('stock.receipt.confirm')) {
            throw ValidationException::withMessages([
                'receipt' => __('catalogue.unauthorized_confirm_receipt'),
            ]);
        }

        if ($receipt->status === 'posted') {
            throw ValidationException::withMessages([
                'receipt' => __('catalogue.receipt_already_posted'),
            ]);
        }

        if ($reject) {
            $receipt->status = 'rejected';
            $receipt->confirmed_by = $actor->id;
            $receipt->confirmed_at = now();
            $receipt->save();

            AuditLogger::record('stock.receipt.rejected', $receipt, [], ['reference' => $receipt->reference], [], $actor);

            return $receipt;
        }

        return DB::transaction(function () use ($receipt, $actor) {
            foreach ($receipt->lines as $line) {
                // A2: If batch exists, get it or create it
                $batch = Batch::firstOrCreate(
                    [
                        'product_id' => $line->product_id,
                        'batch_no' => $line->batch_no,
                    ],
                    [
                        'manufacturing_date' => $line->manufacturing_date,
                        'expiry_date' => $line->expiry_date,
                        'unit_cost' => $line->unit_cost,
                        'supplier_name' => $receipt->supplier_name,
                    ]
                );

                // Damaged quantities go to quarantine, good to sellable (AC-06)
                $status = ($line->flag === 'damaged') ? 'quarantine' : 'sellable';

                // Posting through StockMovementService (the ONLY writer of batch_stocks)
                StockMovementService::post(
                    batch: $batch,
                    signedQuantity: $line->quantity,
                    type: 'receipt',
                    source: $receipt,
                    user: $actor,
                    status: $status,
                    location: null,
                    description: "Stock receipt {$receipt->reference} ({$line->flag})"
                );
            }

            $receipt->status = 'posted';
            $receipt->confirmed_by = $actor->id;
            $receipt->confirmed_at = now();
            $receipt->save();

            AuditLogger::record(
                'stock.receipt.confirmed',
                $receipt,
                ['status' => 'pending_confirmation'],
                ['status' => 'posted', 'confirmed_by' => $actor->id],
                ['reference' => $receipt->reference],
                $actor
            );

            return $receipt;
        });
    }
}
