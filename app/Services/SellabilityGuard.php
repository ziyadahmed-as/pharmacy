<?php

namespace App\Services;

use App\Exceptions\SellabilityException;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\QuarantineRecord;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Enforces BR-01 & BR-10: Never sell expired, recalled, quarantined or inactive stock.
 * Used before sales, holding, and dispensing. Logs blocked attempts in the audit trail (E1).
 *
 * @uc UC-08 @fr FR-2.15 @br BR-01, BR-10 @ac AC-07
 */
class SellabilityGuard
{
    /**
     * Check if product and batch are sellable for the requested quantity and action.
     * Blocked attempts write an audit log entry and throw SellabilityException.
     *
     * @param  string  $action  'sell', 'hold', or 'dispense'
     *
     * @throws SellabilityException
     */
    public static function check(
        Product $product,
        Batch $batch,
        int $quantity = 1,
        ?User $actor = null,
        string $action = 'sell'
    ): void {
        $user = $actor ?? Auth::user();

        // 1. Inactive product
        if (! $product->is_active) {
            self::block(
                reason: 'inactive_product',
                message: __('catalogue.sellability_blocked_inactive', ['brand' => $product->brand]),
                product: $product,
                batch: $batch,
                quantity: $quantity,
                user: $user,
                action: $action
            );
        }

        // 2. Expired batch
        if ($batch->expiry_date && $batch->expiry_date->isPast()) {
            self::block(
                reason: 'expired_batch',
                message: __('catalogue.sellability_blocked_expired', [
                    'brand' => $product->brand,
                    'batch' => $batch->batch_no,
                    'expiry' => $batch->expiry_date->format('Y-m-d'),
                ]),
                product: $product,
                batch: $batch,
                quantity: $quantity,
                user: $user,
                action: $action
            );
        }

        // 3. Recalled batch
        $isRecalled = QuarantineRecord::where('batch_id', $batch->id)
            ->where('status', QuarantineRecord::STATUS_OPEN)
            ->where('reason', QuarantineRecord::REASON_RECALLED)
            ->exists();

        if ($isRecalled) {
            self::block(
                reason: 'recalled_batch',
                message: __('catalogue.sellability_blocked_recalled', [
                    'brand' => $product->brand,
                    'batch' => $batch->batch_no,
                ]),
                product: $product,
                batch: $batch,
                quantity: $quantity,
                user: $user,
                action: $action
            );
        }

        // 4. Quarantined stock or insufficient sellable stock
        $sellableQty = (int) BatchStock::where('batch_id', $batch->id)
            ->where('status', 'sellable')
            ->sum('quantity');

        if ($sellableQty < $quantity) {
            $quarantineQty = (int) BatchStock::where('batch_id', $batch->id)
                ->where('status', 'quarantine')
                ->sum('quantity');

            $reason = ($quarantineQty > 0 && $sellableQty <= 0) ? 'quarantined_stock' : 'insufficient_sellable_stock';
            $message = ($reason === 'quarantined_stock')
                ? __('catalogue.sellability_blocked_quarantined', [
                    'brand' => $product->brand,
                    'batch' => $batch->batch_no,
                    'quarantined' => $quarantineQty,
                ])
                : __('catalogue.sellability_blocked_insufficient', [
                    'brand' => $product->brand,
                    'batch' => $batch->batch_no,
                    'available' => $sellableQty,
                    'requested' => $quantity,
                ]);

            self::block(
                reason: $reason,
                message: $message,
                product: $product,
                batch: $batch,
                quantity: $quantity,
                user: $user,
                action: $action
            );
        }
    }

    /**
     * Non-throwing boolean check for UI / preliminary inspections.
     */
    public static function isSellable(Product $product, Batch $batch, int $quantity = 1): bool
    {
        try {
            self::check($product, $batch, $quantity);

            return true;
        } catch (SellabilityException) {
            return false;
        }
    }

    /**
     * Records the audit log and throws the SellabilityException.
     *
     * @throws SellabilityException
     */
    private static function block(
        string $reason,
        string $message,
        Product $product,
        Batch $batch,
        int $quantity,
        ?User $user,
        string $action
    ): never {
        AuditLogger::record(
            'stock.sellability.blocked',
            $batch,
            [],
            [],
            [
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'quantity' => $quantity,
                'reason' => $reason,
                'action' => $action,
            ],
            $user,
            "Sellability check blocked: {$reason} for {$action}"
        );

        throw new SellabilityException($message, $reason);
    }
}
