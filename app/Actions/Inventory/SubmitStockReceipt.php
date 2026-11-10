<?php

namespace App\Actions\Inventory;

use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\StockReceiptLine;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DocumentSequence;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Submits a new stock receipt.
 *
 * @uc UC-04 @fr FR-2.1, FR-2.2, FR-2.3, FR-2.5 @br BR-08, BR-19 @ac AC-05, AC-06, AC-20
 */
class SubmitStockReceipt
{
    /**
     * @param  array{supplier_name: string, delivery_note_no?: string, receipt_date: string, idempotency_key: string, notes?: string, lines: array}  $data
     *
     * @throws ValidationException
     */
    public function execute(
        array $data,
        ?User $user = null,
        bool $overrideShelfLife = false,
        ?string $overrideReason = null
    ): StockReceipt {
        $actor = $user ?? Auth::user();

        if (! $actor || ! $actor->hasPermission('stock.receive')) {
            throw ValidationException::withMessages([
                'receipt' => __('catalogue.unauthorized_receive_stock'),
            ]);
        }

        // STEP 5: Idempotency (E4)
        $idempotencyKey = $data['idempotency_key'] ?? '';
        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => __('catalogue.missing_idempotency_key'),
            ]);
        }

        $existing = StockReceipt::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing; // Return existing without duplicate creation
        }

        if (empty($data['lines'])) {
            throw ValidationException::withMessages([
                'lines' => __('catalogue.receipt_lines_required'),
            ]);
        }

        $minShelfLifeDays = (int) setting('inventory.minimum_shelf_life_days', 90);
        $receiptDate = Carbon::parse($data['receipt_date'] ?? now());

        // Validate all lines
        foreach ($data['lines'] as $index => $line) {
            $product = Product::find($line['product_id'] ?? null);
            if (! $product) {
                throw ValidationException::withMessages(["lines.{$index}" => __('catalogue.product_not_found')]);
            }

            // E2: Inactive product refused
            if (! $product->is_active) {
                throw ValidationException::withMessages(["lines.{$index}" => __('catalogue.inactive_product_receipt_refused', ['brand' => $product->brand])]);
            }

            if (empty($line['batch_no'])) {
                throw ValidationException::withMessages(["lines.{$index}.batch_no" => __('catalogue.batch_no_required')]);
            }

            if (empty($line['expiry_date'])) {
                throw ValidationException::withMessages(["lines.{$index}.expiry_date" => __('catalogue.expiry_date_required')]);
            }

            $expiry = Carbon::parse($line['expiry_date']);
            $mfg = ! empty($line['manufacturing_date']) ? Carbon::parse($line['manufacturing_date']) : null;

            // Expiry must be later than manufacturing date
            if ($mfg && $expiry->lte($mfg)) {
                throw ValidationException::withMessages(["lines.{$index}.expiry_date" => __('catalogue.expiry_must_be_after_mfg')]);
            }

            // Remaining shelf life check (E1)
            $remainingDays = $receiptDate->diffInDays($expiry, false);
            if ($remainingDays < $minShelfLifeDays) {
                // A1: Below minimum shelf life requires PM override
                if (! $overrideShelfLife || empty($overrideReason) || ! $actor->hasPermission('stock.receipt.confirm')) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.expiry_date" => __('catalogue.short_shelf_life_rejected', [
                            'days' => $remainingDays,
                            'minimum' => $minShelfLifeDays,
                        ]),
                    ]);
                }
            }

            if ((int) ($line['quantity'] ?? 0) <= 0) {
                throw ValidationException::withMessages(["lines.{$index}.quantity" => __('catalogue.quantity_must_be_positive')]);
            }
        }

        return DB::transaction(function () use ($data, $actor, $receiptDate, $overrideReason) {
            $reference = DocumentSequence::next('stock_receipt', 'REC');
            $requiresConfirmation = (bool) setting('inventory.receipt_requires_confirmation', true);

            $receipt = StockReceipt::create([
                'reference' => $reference,
                'supplier_name' => $data['supplier_name'],
                'delivery_note_no' => $data['delivery_note_no'] ?? null,
                'receipt_date' => $receiptDate,
                'status' => $requiresConfirmation ? 'pending_confirmation' : 'posted',
                'idempotency_key' => $data['idempotency_key'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($data['lines'] as $line) {
                $flag = $line['flag'] ?? 'ok';
                StockReceiptLine::create([
                    'stock_receipt_id' => $receipt->id,
                    'product_id' => $line['product_id'],
                    'batch_no' => trim($line['batch_no']),
                    'manufacturing_date' => ! empty($line['manufacturing_date']) ? Carbon::parse($line['manufacturing_date']) : null,
                    'expiry_date' => Carbon::parse($line['expiry_date']),
                    'quantity' => (int) $line['quantity'],
                    'unit_cost' => isset($line['unit_cost']) && $actor->hasPermission('cost.view') ? (int) round(((float) $line['unit_cost']) * 100) : null,
                    'flag' => in_array($flag, ['ok', 'short', 'over', 'damaged'], true) ? $flag : 'ok',
                ]);
            }

            AuditLogger::record(
                'stock.receipt.submitted',
                $receipt,
                [],
                ['reference' => $reference, 'supplier' => $receipt->supplier_name, 'lines_count' => count($data['lines'])],
                ['override_reason' => $overrideReason],
                $actor
            );

            // If confirmation is NOT required, post immediately
            if (! $requiresConfirmation) {
                (new ConfirmStockReceipt)->execute($receipt, $actor);
            }

            return $receipt;
        });
    }
}
