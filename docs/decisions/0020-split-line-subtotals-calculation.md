# Split line subtotals calculation

**Status:** accepted (Defect fix)

## Context
In `app/Actions/Sales/CompleteSale.php`, proportional split line subtotals across multiple FEFO batch allocations previously used `array_slice($plannedLines, -count($allocations) + 1)` to subtract preceding splits from the total line subtotal. When a product had only one allocation (the vast majority of items), `count($allocations)` was 1, leading to `-1 + 1 = 0`. An offset of `0` in `array_slice` returned the entire `$plannedLines` array from earlier cart items, subtracting previous products' subtotals and resulting in incorrect or negative subtotals for subsequent products in multi-item carts.

## Decision
1. In `app/Actions/Sales/CompleteSale.php`, maintain a local `$allocatedSubtotal = 0` per cart item.
2. For each allocation up to `count($allocations) - 1`, assign `(int) round(($lineSubtotal * $allocQty) / $totalAllocatedQty)` and add it to `$allocatedSubtotal`.
3. For the final allocation (`$idx === count($allocations) - 1`), assign the exact remainder `$lineSubtotal - $allocatedSubtotal`.
4. This guarantees that:
   - When a product has a single allocation (`count = 1`), `$allocSubtotal = $lineSubtotal - 0 = $lineSubtotal`.
   - Every line subtotal is strictly non-negative (`>= 0`).
   - The sum of subtotals across all batch split lines for a product always equals `quantity x unit_price`.
   - All sale lines strictly sum to `sales.total`.
5. Added artisan console command `pms:verify-sale-lines` to scan existing completed sales and report any historical records with mismatched or negative line subtotals.
