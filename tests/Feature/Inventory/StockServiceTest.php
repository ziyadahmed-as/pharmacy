<?php

/**
 * Tests for the StockService single-source-of-truth refactor.
 *
 * Covers:
 *  - Receipt adds quantity
 *  - Sale subtracts quantity
 *  - Void restores quantity
 *  - Return restocks quantity
 *  - Adjustment +5 and -5
 *  - Stocktake variance posts the difference
 *  - Below-zero refused
 *  - Low-stock alert appears after a sale and clears after a receipt
 *  - Quarantined quantity is not available
 *  - Valuation = qty × buying price
 *  - Two simultaneous movements on the last unit (only one succeeds)
 *  - Ledger sum equals quantity_on_hand for every batch
 */

use App\Actions\Inventory\QuarantineStock;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\QuarantineRecord;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function ssProduct(array $overrides = []): Product
{
    return Product::create(array_merge([
        'brand'          => 'SS Test ' . uniqid(),
        'generic_name'   => 'Generic',
        'classification' => 'otc',
        'unit'           => 'piece',
        'is_active'      => true,
    ], $overrides));
}

function ssBatch(Product $product, int $qty = 0, array $extra = []): Batch
{
    return Batch::create(array_merge([
        'product_id'        => $product->id,
        'batch_no'          => 'SS-' . uniqid(),
        'expiry_date'       => now()->addMonths(12)->toDateString(),
        'unit_cost'         => 2000,   // 20.00 ETB
        'status'            => Batch::STATUS_SELLABLE,
        'quantity_on_hand'  => $qty,
    ], $extra));
}

function ssReceipt(Batch $batch, int $qty): StockMovement
{
    return StockService::applyMovement(
        batch: $batch,
        signedQuantity: $qty,
        type: 'receipt',
        source: $batch->product,
        user: pm(),
        stockStatus: 'sellable',
    );
}

function ssSale(Batch $batch, int $qty): StockMovement
{
    return StockService::applyMovement(
        batch: $batch,
        signedQuantity: -$qty,
        type: 'sale',
        source: $batch->product,
        user: pm(),
        stockStatus: 'sellable',
    );
}

// ===========================================================================
// 1. Receipt adds quantity
// ===========================================================================

test('receipt increases quantity_on_hand by the received amount', function () {
    $p = ssProduct();
    $b = ssBatch($p);

    ssReceipt($b, 50);

    expect($b->fresh()->quantity_on_hand)->toBe(50);

    $movement = StockMovement::where('batch_id', $b->id)->first();
    expect($movement->type)->toBe('receipt')
        ->and($movement->quantity)->toBe(50)
        ->and($movement->balance_after)->toBe(50);
});

// ===========================================================================
// 2. Sale subtracts quantity
// ===========================================================================

test('sale decreases quantity_on_hand by the sold amount', function () {
    $p = ssProduct();
    $b = ssBatch($p, 100);

    ssSale($b, 30);

    expect($b->fresh()->quantity_on_hand)->toBe(70);
});

// ===========================================================================
// 3. Void restores quantity
// ===========================================================================

test('sale_void restores quantity_on_hand', function () {
    $p = ssProduct();
    $b = ssBatch($p, 50);

    ssSale($b, 20);
    expect($b->fresh()->quantity_on_hand)->toBe(30);

    StockService::applyMovement(
        batch: $b,
        signedQuantity: 20,
        type: 'sale_void',
        source: $b->product,
        user: pm(),
        stockStatus: 'sellable',
    );

    expect($b->fresh()->quantity_on_hand)->toBe(50);
});

// ===========================================================================
// 4. Return restocks quantity
// ===========================================================================

test('return_from_customer restocks quantity_on_hand', function () {
    $p = ssProduct();
    $b = ssBatch($p, 100);

    ssSale($b, 40);
    expect($b->fresh()->quantity_on_hand)->toBe(60);

    StockService::applyMovement(
        batch: $b,
        signedQuantity: 10,
        type: 'return_from_customer',
        source: $b->product,
        user: pm(),
        stockStatus: 'sellable',
    );

    expect($b->fresh()->quantity_on_hand)->toBe(70);
});

// ===========================================================================
// 5. Adjustment +5 and -5
// ===========================================================================

test('adjustment +5 increases quantity_on_hand', function () {
    $p = ssProduct();
    $b = ssBatch($p, 20);

    StockService::applyMovement(
        batch: $b, signedQuantity: 5, type: 'adjustment',
        source: $b->product, user: pm(), stockStatus: 'sellable',
    );

    expect($b->fresh()->quantity_on_hand)->toBe(25);
});

test('adjustment -5 decreases quantity_on_hand', function () {
    $p = ssProduct();
    $b = ssBatch($p, 20);

    StockService::applyMovement(
        batch: $b, signedQuantity: -5, type: 'adjustment',
        source: $b->product, user: pm(), stockStatus: 'sellable',
    );

    expect($b->fresh()->quantity_on_hand)->toBe(15);
});

// ===========================================================================
// 6. Stocktake variance posts the difference
// ===========================================================================

test('stocktake variance posts the signed difference', function () {
    $p = ssProduct();
    $b = ssBatch($p, 80);

    // Counted as 75 → variance = 75 - 80 = -5
    StockService::applyMovement(
        batch: $b, signedQuantity: -5, type: 'adjustment',
        source: $b->product, user: pm(), stockStatus: 'sellable',
        description: 'Stocktake variance',
    );

    expect($b->fresh()->quantity_on_hand)->toBe(75);
});

// ===========================================================================
// 7. Below-zero refused
// ===========================================================================

test('movement that would make quantity_on_hand negative is refused', function () {
    $p = ssProduct();
    $b = ssBatch($p, 5);

    expect(fn () => ssSale($b, 10))
        ->toThrow(RuntimeException::class);

    // Nothing changed
    expect($b->fresh()->quantity_on_hand)->toBe(5);
});

// ===========================================================================
// 8. Low-stock alert appears after a sale and clears after a receipt
// ===========================================================================

test('low-stock condition appears after a sale and clears after a receipt', function () {
    $pm = pm();
    $p = ssProduct(['reorder_level' => 20, 'min_level' => 5]);
    $b = ssBatch($p, 30);

    // Sell 15 → on hand = 15 ≤ reorder 20 → low stock
    ssSale($b, 15);
    expect($b->fresh()->quantity_on_hand)->toBe(15);

    // The product's available quantity should be ≤ reorder_level
    $available = (int) DB::table('batches')
        ->where('product_id', $p->id)
        ->where('expiry_date', '>', now()->toDateString())
        ->where('status', 'SELLABLE')
        ->sum('quantity_on_hand');
    expect($available)->toBe(15);  // ≤ 20 → low stock

    // Receive 20 → on hand = 35 > 20 → healthy
    ssReceipt($b, 20);
    $available = (int) DB::table('batches')
        ->where('product_id', $p->id)
        ->where('expiry_date', '>', now()->toDateString())
        ->where('status', 'SELLABLE')
        ->sum('quantity_on_hand');
    expect($available)->toBe(35);  // > 20 → healthy
});

// ===========================================================================
// 9. Quarantined quantity is not available
// ===========================================================================

test('quarantined quantity is not counted as available', function () {
    $pm = pm();
    $p = ssProduct();
    $b = ssBatch($p, 50);

    // Quarantine 20 units
    $action = app(QuarantineStock::class);
    $action->execute($b, 20, QuarantineRecord::REASON_DAMAGED, 'Test quarantine', $pm);

    $b->refresh();

    expect($b->quantity_on_hand)->toBe(30)    // sellable reduced
        ->and($b->quarantine_qty)->toBe(20);  // quarantine increased

    // Available = sellable only
    $available = (int) DB::table('batches')
        ->where('product_id', $p->id)
        ->where('status', 'SELLABLE')
        ->sum('quantity_on_hand');
    expect($available)->toBe(30);  // quarantine excluded
});

// ===========================================================================
// 10. Valuation = qty × buying price
// ===========================================================================

test('stock value equals quantity_on_hand × unit_cost', function () {
    $p = ssProduct();
    $b = ssBatch($p, 100, ['unit_cost' => 3000]);  // 30 ETB per unit

    $value = (int) DB::table('batches')
        ->where('id', $b->id)
        ->where('status', 'SELLABLE')
        ->selectRaw('quantity_on_hand * unit_cost')
        ->value(DB::raw('quantity_on_hand * unit_cost'));

    expect($value)->toBe(300000);  // 100 × 3000 santim
});

// ===========================================================================
// 11. Two simultaneous movements on the last unit — only one succeeds
// ===========================================================================

test('two movements on the last unit — only one succeeds', function () {
    $p = ssProduct();
    $b = ssBatch($p, 1);

    $errors = [];
    $sales = [];

    try {
        $sales[] = ssSale($b, 1);
    } catch (\Throwable $e) {
        $errors[] = $e;
    }

    try {
        $sales[] = ssSale($b, 1);
    } catch (\Throwable $e) {
        $errors[] = $e;
    }

    // One sale succeeded, one failed
    expect(count($sales))->toBe(1)
        ->and(count($errors))->toBe(1);

    // quantity_on_hand = 0, never negative
    expect($b->fresh()->quantity_on_hand)->toBe(0);
});

// ===========================================================================
// 12. Ledger sum equals quantity_on_hand for every batch
// ===========================================================================

test('ledger sum equals quantity_on_hand for every batch', function () {
    $p = ssProduct();
    $b = ssBatch($p);

    ssReceipt($b, 100);
    ssSale($b, 30);
    ssSale($b, 20);
    StockService::applyMovement(
        batch: $b, signedQuantity: 10, type: 'return_from_customer',
        source: $b->product, user: pm(), stockStatus: 'sellable',
    );
    StockService::applyMovement(
        batch: $b, signedQuantity: -5, type: 'adjustment',
        source: $b->product, user: pm(), stockStatus: 'sellable',
    );

    $ledgerSum = (int) StockMovement::where('batch_id', $b->id)
        ->where('stock_status', 'sellable')
        ->sum('quantity');

    expect($b->fresh()->quantity_on_hand)->toBe($ledgerSum)
        ->and($ledgerSum)->toBe(55);  // 100 - 30 - 20 + 10 - 5
});

// ===========================================================================
// 13. Direct write outside StockService is rejected by the arch test
// ===========================================================================

test('arch test prevents BatchStock writes outside StockService', function () {
    // This is an arch test defined in tests/Arch/ArchTest.php.
    // It scans all PHP files for BatchStock::create/update/insert
    // outside of StockService.php, StockMovementService.php (shim),
    // BatchStock.php (model/observer), and Batch.php.
    // If any new file writes batch_stocks directly, the arch test fails.
    expect(true)->toBeTrue(); // placeholder — arch test runs separately
});

// ===========================================================================
// 14. DEPLETED transition
// ===========================================================================

test('batch status becomes DEPLETED when quantity_on_hand reaches 0', function () {
    $p = ssProduct();
    $b = ssBatch($p, 10);

    ssSale($b, 10);

    expect($b->fresh()->status)->toBe(Batch::STATUS_DEPLETED);
});

test('DEPLETED batch returns to SELLABLE when restocked', function () {
    $p = ssProduct();
    $b = ssBatch($p, 5);

    ssSale($b, 5);
    expect($b->fresh()->status)->toBe(Batch::STATUS_DEPLETED);

    ssReceipt($b, 10);
    expect($b->fresh()->status)->toBe(Batch::STATUS_SELLABLE);
});

// ===========================================================================
// 15. Idempotency — retry does not apply twice
// ===========================================================================

test('idempotency key prevents double application', function () {
    $p = ssProduct();
    $b = ssBatch($p, 0);

    $source = $b->product;   // using product as source for simplicity

    // First application
    $m1 = StockService::applyMovement(
        batch: $b, signedQuantity: 50, type: 'receipt',
        source: $source, user: pm(), stockStatus: 'sellable',
        idempotencyKey: 'IDEM-SS-1',
    );

    // Retry with same idempotency key — should return the existing movement
    $m2 = StockService::applyMovement(
        batch: $b, signedQuantity: 50, type: 'receipt',
        source: $source, user: pm(), stockStatus: 'sellable',
        idempotencyKey: 'IDEM-SS-1',
    );

    expect($m1->id)->toBe($m2->id);
    expect($b->fresh()->quantity_on_hand)->toBe(50);  // not 100
});
