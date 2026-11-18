<?php

/**
 * Comprehensive tests for sale stock deduction (UC-13, FR-2.4, FR-2.5,
 * BR-02, BR-04, BR-05, BR-08).
 *
 * Covers: single-batch sale, multi-batch split, DEPLETED transition,
 * over-available refusal, concurrent last-unit sale, double-click idempotency,
 * mid-sale rollback, void restores stock, return to expired batch → quarantine,
 * ledger balance equals quantity_on_hand, unit_cost snapshot, loose_allowed enforcement.
 */

use App\Actions\Sales\CompleteSale;
use App\Actions\Sales\ProcessReturn;
use App\Approvals\Handlers\VoidSaleHandler;
use App\Models\ApprovalRequest;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleReturn;
use App\Models\SaleReturnLine;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Till;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function sdProduct(array $overrides = []): Product
{
    return Product::create(array_merge([
        'brand'         => 'SD Test ' . uniqid(),
        'generic_name'  => 'Generic',
        'classification'=> 'otc',
        'unit'         => 'piece',
        'is_active'    => true,
    ], $overrides));
}

function sdPrice(Product $product, int $santim): void
{
    ProductPrice::create([
        'product_id'    => $product->id,
        'unit_id'       => null,
        'price'         => $santim,
        'effective_from'=> now(),
        'reason'        => 'Test',
        'set_by'        => pm()->id,
        'created_at'    => now(),
    ]);
}

function sdBatch(Product $product, string $expiry, int $qty, int $cost = 1000): Batch
{
    $batch = Batch::create([
        'product_id'  => $product->id,
        'batch_no'    => 'SD-' . uniqid(),
        'expiry_date' => $expiry,
        'unit_cost'   => $cost,
        'status'      => Batch::STATUS_SELLABLE,
    ]);
    StockMovementService::post($batch, $qty, 'receipt', $product, pm(), 'sellable');
    return $batch->fresh();
}

function sdShift(User $user): array
{
    $till  = Till::create(['name' => 'SD-TILL-' . uniqid(), 'is_active' => true]);
    $shift = Shift::create([
        'till_id'       => $till->id,
        'user_id'       => $user->id,
        'opened_at'     => now(),
        'opening_float' => 1000,
        'status'        => 'open',
    ]);
    return [$shift, $till];
}

function sdCompleteSale(User $user, array $items, string $idemKey, ?int $prescriptionId = null, ?int $customerId = null): Sale
{
    $totalSantim = 0;
    foreach ($items as $item) {
        $product = Product::find($item['product_id']);
        $unit    = !empty($item['unit_id']) ? ProductUnit::find($item['unit_id']) : null;
        $price   = \App\Services\PriceResolver::at($product, $unit);
        // CompleteSale computes subtotal as quantityInUnit × unitPriceSantim
        // (the price already reflects the chosen unit — no factor multiplication).
        $totalSantim += $item['quantity'] * (int) $price->price;
    }

    return (new CompleteSale)->execute(
        user: $user,
        items: $items,
        payments: [['method' => 'cash', 'amount' => $totalSantim, 'reference' => '']],
        idempotencyKey: $idemKey,
        prescriptionId: $prescriptionId,
        customerId: $customerId,
    );
}

// ===========================================================================
// 1. Single-batch sale reduces quantity
// ===========================================================================

test('single-batch sale reduces batch quantity by the sold amount', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 500);
    $batch  = sdBatch($product, now()->addMonths(6)->toDateString(), 50, 2000);

    $sale = sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 10],
    ], 'SD-SINGLE-1');

    $sellable = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->first();
    expect($sellable->quantity)->toBe(40);

    $line = SaleLine::where('sale_id', $sale->id)->first();
    expect($line->quantity)->toBe(10)
        ->and($line->batch_id)->toBe($batch->id)
        ->and($line->unit_cost)->toBe(2000);
});

// ===========================================================================
// 2. Sale split across two batches reduces both correctly
// ===========================================================================

test('sale split across two batches reduces both correctly', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 300);

    // Earlier-expiring batch (FEFO picks this first) with 15 units.
    $batchA = sdBatch($product, now()->addMonths(3)->toDateString(), 15);
    // Later-expiring batch with 25 units.
    $batchB = sdBatch($product, now()->addMonths(9)->toDateString(), 25);

    // Sell 20: FEFO takes 15 from batchA, 5 from batchB.
    $sale = sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 20],
    ], 'SD-SPLIT-1');

    $qtyA = BatchStock::where('batch_id', $batchA->id)->where('status', 'sellable')->sum('quantity');
    $qtyB = BatchStock::where('batch_id', $batchB->id)->where('status', 'sellable')->sum('quantity');

    expect($qtyA)->toBe(0)    // 15 - 15 = 0
        ->and($qtyB)->toBe(20); // 25 - 5 = 20

    // Two sale lines should exist — one per batch.
    $lines = SaleLine::where('sale_id', $sale->id)->orderBy('id')->get();
    expect($lines)->toHaveCount(2)
        ->and($lines[0]->batch_id)->toBe($batchA->id)
        ->and($lines[0]->quantity)->toBe(15)
        ->and($lines[1]->batch_id)->toBe($batchB->id)
        ->and($lines[1]->quantity)->toBe(5);
});

// ===========================================================================
// 3. Selling the last unit sets batch status DEPLETED
// ===========================================================================

test('selling the last unit sets batch status to DEPLETED', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 500);
    $batch = sdBatch($product, now()->addMonths(6)->toDateString(), 5);

    expect($batch->fresh()->status)->toBe(Batch::STATUS_SELLABLE);

    sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 5],
    ], 'SD-DEPL-1');

    expect($batch->fresh()->status)->toBe(Batch::STATUS_DEPLETED);
});

// ===========================================================================
// 4. Selling more than available is refused and nothing changes
// ===========================================================================

test('selling more than available is refused and nothing changes', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 500);
    $batch = sdBatch($product, now()->addMonths(6)->toDateString(), 10);

    expect(fn () => sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 15],
    ], 'SD-OVER-1'))
        ->toThrow(\App\Exceptions\InsufficientStockException::class);

    // Stock must be unchanged.
    $qty = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');
    expect($qty)->toBe(10);

    // No sale, no sale_line, no movement beyond the original receipt.
    expect(Sale::where('idempotency_key', 'SD-OVER-1')->count())->toBe(0)
        ->and(SaleLine::where('batch_id', $batch->id)->count())->toBe(0)
        ->and(StockMovement::where('batch_id', $batch->id)->where('type', 'sale')->count())->toBe(0);
});

// ===========================================================================
// 5. Two concurrent sales of the last unit — only one succeeds
// ===========================================================================

test('two concurrent sales of the last unit — only one succeeds', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 500);
    $batch = sdBatch($product, now()->addMonths(6)->toDateString(), 1);

    $results = [];
    $errors = [];

    // Simulate concurrency by running two sales in the same process.
    // The database transaction + lockForUpdate ensures only one succeeds.
    // In SQLite (in-memory, single connection) the lock is advisory;
    // the real guarantee is the idempotency/transaction model.
    // We verify: at most 1 sale_line, stock never goes negative.
    try {
        $results[] = sdCompleteSale($pmUser, [
            ['product_id' => $product->id, 'quantity' => 1],
        ], 'SD-CONC-1A');
    } catch (\Throwable $e) {
        $errors[] = $e;
    }

    try {
        $results[] = sdCompleteSale($pmUser, [
            ['product_id' => $product->id, 'quantity' => 1],
        ], 'SD-CONC-1B');
    } catch (\Throwable $e) {
        $errors[] = $e;
    }

    // Exactly one sale succeeded; the other must have failed (insufficient stock).
    $saleCount = Sale::whereIn('idempotency_key', ['SD-CONC-1A', 'SD-CONC-1B'])->count();
    expect($saleCount)->toBe(1)
        ->and(count($errors))->toBe(1);

    // Stock must be 0 (never negative).
    $qty = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');
    expect($qty)->toBe(0);
});

// ===========================================================================
// 6. Double-click / resend deducts stock only once (idempotency)
// ===========================================================================

test('double submit with same idempotency key deducts stock only once', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 500);
    $batch = sdBatch($product, now()->addMonths(6)->toDateString(), 20);

    $sale1 = sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 5],
    ], 'SD-IDEM-1');

    $sale2 = sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 5],
    ], 'SD-IDEM-1');  // same key

    // Same sale returned.
    expect($sale1->id)->toBe($sale2->id);

    // Only 5 deducted, not 10.
    $qty = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');
    expect($qty)->toBe(15);

    // Only one sale record.
    expect(Sale::where('idempotency_key', 'SD-IDEM-1')->count())->toBe(1);
});

// ===========================================================================
// 7. Failure in the middle rolls back stock, sale and ledger
// ===========================================================================

test('payment mismatch rolls back stock, sale and ledger', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 500);
    $batch = sdBatch($product, now()->addMonths(6)->toDateString(), 30);

    // Deliberately pass wrong payment amount (total is 2500 santim for 5 units).
    expect(fn () => (new CompleteSale)->execute(
        user: $pmUser,
        items: [['product_id' => $product->id, 'quantity' => 5]],
        payments: [['method' => 'cash', 'amount' => 999, 'reference' => '']],
        idempotencyKey: 'SD-ROLL-1',
    ))->toThrow(\App\Exceptions\PaymentMismatchException::class);

    // Stock must be unchanged — rollback.
    $qty = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');
    expect($qty)->toBe(30)
        ->and(Sale::where('idempotency_key', 'SD-ROLL-1')->count())->toBe(0)
        ->and(SaleLine::where('batch_id', $batch->id)->count())->toBe(0)
        ->and(StockMovement::where('batch_id', $batch->id)->where('type', 'sale')->count())->toBe(0);
});

// ===========================================================================
// 8. Void restores the same batches
// ===========================================================================

test('void restores sold quantities to the same batches', function () {
    $pmUser = pm();
    $owner  = owner();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 500);
    $batch = sdBatch($product, now()->addMonths(6)->toDateString(), 20);

    $sale = sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 8],
    ], 'SD-VOID-1');

    // Stock reduced to 12.
    expect(BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity'))->toBe(12);

    // Simulate the void approval handler.
    $approval = ApprovalRequest::create([
        'initiator_id'     => $pmUser->id,
        'type'             => 'sale.void',
        'status'           => 'approved',
        'summary'          => 'Void test',
        'payload'          => ['sale_id' => $sale->id, 'reason' => 'Test void'],
        'eligible_roles'   => json_encode(['owner']),
        'created_at'       => now(),
        'updated_at'       => now(),
    ]);

    (new VoidSaleHandler)->onApproved($approval, $owner);

    // Stock restored to 20.
    expect(BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity'))->toBe(20)
        ->and($sale->fresh()->status)->toBe('voided');

    // SALE_VOID ledger movement exists with positive quantity.
    $voidMovement = StockMovement::where('batch_id', $batch->id)
        ->where('type', 'sale_void')
        ->first();
    expect($voidMovement)->not->toBeNull()
        ->and($voidMovement->quantity)->toBe(8)
        ->and($voidMovement->balance_after)->toBe(20);
});

// ===========================================================================
// 9. Return to expired batch goes to quarantine, not sellable
// ===========================================================================

test('customer return to an expired batch goes to quarantine not sellable', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 500);

    // Set a high refund limit so the return completes immediately (no approval needed).
    \App\Models\Setting::updateOrCreate(
        ['key' => 'sales.refund_limit'],
        ['group' => 'sales', 'value' => 100000, 'type' => 'money', 'description' => 'Test limit', 'is_sensitive' => false]
    );
    app(\App\Services\SettingsRepository::class)->flush();

    // Create a batch that will expire soon, sell from it, then let it expire.
    $batch = sdBatch($product, now()->addDay()->toDateString(), 30);

    $sale = sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 10],
    ], 'SD-RET-EXP-1');

    // Simulate the batch expiring by updating its expiry_date to the past.
    Batch::where('id', $batch->id)->update(['expiry_date' => now()->subDay()]);

    // Process a return of 3 units, assessed as resaleable.
    $result = (new ProcessReturn(app(\App\Services\ApprovalService::class)))->execute(
        user: $pmUser,
        receiptNo: $sale->receipt_no,
        items: [
            [
                'sale_line_id' => $sale->lines->first()->id,
                'quantity'     => 3,
                'assessment'   => SaleReturnLine::ASSESSMENT_RESALEABLE,
            ],
        ],
    );

    $saleReturn = $result['sale_return'];

    // Stock must NOT go back to sellable — it goes to quarantine because the batch expired.
    $sellableQty = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');
    $quarantineQty = BatchStock::where('batch_id', $batch->id)->where('status', 'quarantine')->sum('quantity');

    expect($sellableQty)->toBe(20)   // 30 - 10 sold, no return added back
        ->and($quarantineQty)->toBe(3); // returned 3 went to quarantine

    // The movement type must be quarantine, not return.
    $returnMovement = StockMovement::where('batch_id', $batch->id)
        ->where('type', 'quarantine')
        ->orderByDesc('id')
        ->first();
    expect($returnMovement)->not->toBeNull()
        ->and($returnMovement->quantity)->toBe(3);
});

// ===========================================================================
// 10. Ledger balance always equals quantity_on_hand
// ===========================================================================

test('ledger balance_after always equals the live BatchStock quantity', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 500);
    $batch = sdBatch($product, now()->addMonths(6)->toDateString(), 100);

    // Sell 30.
    sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 30],
    ], 'SD-LEDGER-1');

    // Sell another 25.
    sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 25],
    ], 'SD-LEDGER-2');

    $liveQty = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');
    $lastMovement = StockMovement::where('batch_id', $batch->id)
        ->orderByDesc('id')
        ->first();

    expect($lastMovement->balance_after)->toBe($liveQty)
        ->and($liveQty)->toBe(45); // 100 - 30 - 25
});

// ===========================================================================
// 11. unit_cost snapshot stored on sale_line
// ===========================================================================

test('sale_line stores the batch unit_cost at the moment of sale', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 800);
    $batch = sdBatch($product, now()->addMonths(6)->toDateString(), 10, 3500);

    $sale = sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 3],
    ], 'SD-COST-1');

    $line = SaleLine::where('sale_id', $sale->id)->first();
    expect($line->unit_cost)->toBe(3500)
        ->and($line->unit_price)->toBe(800);
});

// ===========================================================================
// 12. loose_allowed enforcement — server-side, not just UI
// ===========================================================================

test('loose sale with a non-base unit is refused when product does not allow loose', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct(['loose_allowed' => false]);
    sdPrice($product, 500);

    // Create an alternative unit (box of 10) for this product.
    $box = ProductUnit::create([
        'product_id' => $product->id,
        'name'       => 'box',
        'factor'     => 10,
    ]);

    // Also create a price for the box unit.
    ProductPrice::create([
        'product_id'    => $product->id,
        'unit_id'       => $box->id,
        'price'         => 4500,
        'effective_from' => now(),
        'reason'        => 'Box price',
        'set_by'        => $pmUser->id,
        'created_at'    => now(),
    ]);

    sdBatch($product, now()->addMonths(6)->toDateString(), 100);

    // Attempting to sell by box should be refused server-side.
    expect(fn () => sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 1, 'unit_id' => $box->id],
    ], 'SD-LOOSE-1'))
        ->toThrow(\InvalidArgumentException::class);

    // No sale created.
    expect(Sale::where('idempotency_key', 'SD-LOOSE-1')->count())->toBe(0);
});

test('loose sale is allowed when product flags loose_allowed', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct(['loose_allowed' => true]);
    sdPrice($product, 500);

    $box = ProductUnit::create([
        'product_id' => $product->id,
        'name'       => 'box',
        'factor'     => 10,
    ]);

    ProductPrice::create([
        'product_id'    => $product->id,
        'unit_id'       => $box->id,
        'price'         => 4500,
        'effective_from'=> now(),
        'reason'        => 'Box price',
        'set_by'        => $pmUser->id,
        'created_at'    => now(),
    ]);

    sdBatch($product, now()->addMonths(6)->toDateString(), 100);

    // Selling 1 box = 10 base units. Should succeed.
    $sale = sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 1, 'unit_id' => $box->id],
    ], 'SD-LOOSE-OK');

    $line = SaleLine::where('sale_id', $sale->id)->first();
    expect($line->quantity)->toBe(10); // converted to base units
});

// ===========================================================================
// 13. Adding to cart does NOT deduct stock
// ===========================================================================

test('adding to cart does not deduct stock — no movement is posted', function () {
    $product = sdProduct();
    sdPrice($product, 500);
    $batch = sdBatch($product, now()->addMonths(6)->toDateString(), 20);

    $movementsBefore = StockMovement::where('batch_id', $batch->id)->count();

    // Simulate the cart add (as the Livewire component does internally).
    $eligible = \App\Services\FefoBatchResolver::getEligibleBatches($product);
    expect($eligible)->not->toBeEmpty();

    // No new movements — just reading.
    $movementsAfter = StockMovement::where('batch_id', $batch->id)->count();
    expect($movementsAfter)->toBe($movementsBefore);
});

// ===========================================================================
// 14. FEFO override requires a reason and is recorded on the sale_line
// ===========================================================================

test('FEFO override with a reason selects the chosen batch and records the reason', function () {
    $pmUser = pm();
    [$shift] = sdShift($pmUser);

    $product = sdProduct();
    sdPrice($product, 500);

    $batchA = sdBatch($product, now()->addMonths(3)->toDateString(), 20);  // earliest (FEFO default)
    $batchB = sdBatch($product, now()->addMonths(9)->toDateString(), 20);  // later

    // Override to batchB with a reason.
    $sale = sdCompleteSale($pmUser, [
        ['product_id' => $product->id, 'quantity' => 5, 'batch_id' => $batchB->id, 'override_reason' => 'Customer requested specific batch'],
    ], 'SD-OVERRIDE-1');

    $line = SaleLine::where('sale_id', $sale->id)->first();
    expect($line->batch_id)->toBe($batchB->id)
        ->and($line->is_fefo_override)->toBeTrue()
        ->and($line->override_reason)->toBe('Customer requested specific batch');
});
