<?php

namespace App\Actions\Sales;

use App\Exceptions\NoOpenShiftException;
use App\Models\HeldSale;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CurrentShift;
use App\Services\FefoBatchResolver;
use App\Services\PriceResolver;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Resume a held sale with real-time re-validation of stock, expiry, and current price.
 *
 * @uc UC-14
 *
 * @fr FR-3.6
 *
 * @br BR-01, BR-02, BR-20
 *
 * @ac AC-23
 */
class ResumeSale
{
    /**
     * Reload and re-validate every cart line from a held sale.
     *
     * @param  User  $user  Cashier resuming the sale
     * @param  HeldSale  $heldSale  The held transaction
     * @param  Shift|null  $shift  Current active cash shift
     * @return array{held_sale: HeldSale, cart: array<int, array<string, mixed>>, warnings: array<int, string>, has_issues: bool}
     *
     * @throws AuthorizationException
     * @throws NoOpenShiftException
     * @throws InvalidArgumentException
     */
    public function execute(
        User $user,
        HeldSale $heldSale,
        ?Shift $shift = null
    ): array {
        if ($user->isSystemAdmin()) {
            throw new AuthorizationException(__('sales.unauthorized_to_sell'));
        }

        // AC-23: Staff only see and resume their own held sales
        if ($heldSale->user_id !== $user->id && ! $user->hasAnyRole(['pharmacy_manager', 'owner'])) {
            throw new AuthorizationException(__('sales.unauthorized_held_sale_access'));
        }

        if (! $heldSale->isHeld()) {
            throw new InvalidArgumentException(__('sales.held_sale_already_processed'));
        }

        $activeShift = $shift ?? CurrentShift::requireForUser($user);
        if (! $activeShift->isOpen()) {
            throw new NoOpenShiftException(__('shifts.no_open_shift_error'));
        }

        $warnings = [];
        $rawCart = (array) $heldSale->cart;
        $revalidatedCart = [];

        foreach ($rawCart as $line) {
            $product = Product::with(['units'])->find($line['product_id']);

            // 1. Inactive or missing product check
            if (! $product || ! $product->is_active) {
                $line['invalid'] = true;
                $line['error_reason'] = __('sales.product_no_longer_available', ['brand' => $line['brand'] ?? 'Item']);
                $warnings[] = $line['error_reason'];
                $revalidatedCart[] = $line;

                continue;
            }

            $unit = ! empty($line['unit_id']) ? ProductUnit::find($line['unit_id']) : null;
            $factor = $unit ? $unit->factor : 1;
            $qtyInBaseUnits = $line['quantity'] * $factor;

            // 2. Price Re-validation (A1: price changed)
            $currentPrice = PriceResolver::at($product, $unit);
            if (! $currentPrice) {
                $line['invalid'] = true;
                $line['error_reason'] = __('sales.no_price_configured', ['product' => $product->brand]);
                $warnings[] = $line['error_reason'];
            } else {
                $currentPriceSantim = (int) $currentPrice->price;
                if ($currentPriceSantim !== (int) $line['unit_price']) {
                    $oldPriceEtb = number_format($line['unit_price'] / 100, 2);
                    $newPriceEtb = number_format($currentPriceSantim / 100, 2);

                    $line['price_changed'] = true;
                    $line['old_price'] = $line['unit_price'];
                    $line['unit_price'] = $currentPriceSantim;
                    $line['subtotal'] = $line['quantity'] * $currentPriceSantim;

                    $msg = __('sales.price_changed_warning', [
                        'brand' => $product->brand,
                        'old' => $oldPriceEtb,
                        'new' => $newPriceEtb,
                    ]);
                    $line['price_warning'] = $msg;
                    $warnings[] = $msg;
                }
            }

            // 3. Stock, Expiry & Quarantine Re-validation (A1, E1)
            $eligibleBatches = FefoBatchResolver::getEligibleBatches($product);
            $totalAvailableStock = (int) $eligibleBatches->sum(fn ($b) => $b->batchStocks->sum('quantity'));

            if ($totalAvailableStock <= 0) {
                $line['sold_out'] = true;
                $line['invalid'] = true;
                $msg = __('sales.product_sold_out_warning', ['brand' => $product->brand]);
                $line['stock_warning'] = $msg;
                $warnings[] = $msg;
            } elseif ($totalAvailableStock < $qtyInBaseUnits) {
                $line['insufficient_stock'] = true;
                $line['available_stock'] = (int) floor($totalAvailableStock / $factor);
                $msg = __('sales.stock_reduced_warning', [
                    'brand' => $product->brand,
                    'available' => $line['available_stock'],
                    'requested' => $line['quantity'],
                ]);
                $line['stock_warning'] = $msg;
                $warnings[] = $msg;
            } else {
                // Stock is sufficient; ensure batch is valid FEFO batch
                $earliest = $eligibleBatches->first();
                if ($earliest) {
                    $line['batch_id'] = $earliest->id;
                    $line['batch_no'] = $earliest->batch_no;
                    $line['expiry_date'] = $earliest->expiry_date?->format('Y-m-d') ?? '-';
                }
                $line['sold_out'] = false;
                $line['insufficient_stock'] = false;
            }

            $revalidatedCart[] = $line;
        }

        // Mark HeldSale as resumed
        $heldSale->status = HeldSale::STATUS_RESUMED;
        $heldSale->resumed_at = now();
        $heldSale->save();

        AuditLogger::record(
            action: 'sale.resumed',
            subject: $heldSale,
            old: ['status' => HeldSale::STATUS_HELD],
            new: ['status' => HeldSale::STATUS_RESUMED, 'resumed_at' => $heldSale->resumed_at],
            metadata: [
                'held_sale_id' => $heldSale->id,
                'items_count' => count($revalidatedCart),
                'warnings_count' => count($warnings),
            ],
            user: $user,
            description: "Held sale #{$heldSale->id} resumed by {$user->name} (".count($warnings).' flags)'
        );

        return [
            'held_sale' => $heldSale,
            'cart' => $revalidatedCart,
            'warnings' => $warnings,
            'has_issues' => ! empty($warnings),
        ];
    }
}
