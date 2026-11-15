<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Sales\CompleteSale;
use App\Actions\Shifts\OpenShift;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Till;
use Carbon\Carbon;
use Illuminate\Support\Str;

beforeEach(fn () => seedRoles());

function createSubtotalTestProduct(string $brand): Product
{
    return Product::create([
        'brand' => $brand,
        'generic_name' => $brand.' Generic',
        'dosage_form' => 'tablet',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'tablet',
        'is_active' => true,
        'loose_allowed' => true,
    ]);
}

function createSubtotalTestBatch(Product $product, string $batchNo, int $quantity, Carbon $expiry): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => $batchNo,
        'expiry_date' => $expiry->format('Y-m-d'),
        'unit_cost' => 1000,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $quantity,
        'status' => 'sellable',
    ]);

    return $batch;
}

function setSubtotalTestPrice(Product $product, int $priceSantim): ProductPrice
{
    return app(SetSellingPrice::class)->execute(
        product: $product,
        price: $priceSantim,
        effectiveFrom: now()->subMinute(),
        reason: 'Price for test',
        unit: null,
        user: pm()
    );
}

function openShiftForSubtotalTest($user): Shift
{
    $till = Till::firstOrCreate(['name' => 'Subtotal Till'], ['is_active' => true]);

    return app(OpenShift::class)->execute($user, $till, 10000);
}

test('(a) cart of 3 products each from one batch has positive subtotals and sums to total', function () {
    $staff = ps();
    openShiftForSubtotalTest($staff);

    $p1 = createSubtotalTestProduct('Product 1');
    $p2 = createSubtotalTestProduct('Product 2');
    $p3 = createSubtotalTestProduct('Product 3');

    createSubtotalTestBatch($p1, 'B1', 100, now()->addYear());
    createSubtotalTestBatch($p2, 'B2', 100, now()->addYear());
    createSubtotalTestBatch($p3, 'B3', 100, now()->addYear());

    setSubtotalTestPrice($p1, 1000); // 10.00 ETB
    setSubtotalTestPrice($p2, 1500); // 15.00 ETB
    setSubtotalTestPrice($p3, 2500); // 25.00 ETB

    // Quantities: p1 = 2 (2000 santim), p2 = 3 (4500 santim), p3 = 1 (2500 santim) -> Total = 9000 santim
    $items = [
        ['product_id' => $p1->id, 'quantity' => 2],
        ['product_id' => $p2->id, 'quantity' => 3],
        ['product_id' => $p3->id, 'quantity' => 1],
    ];

    $totalExpected = (2 * 1000) + (3 * 1500) + (1 * 2500); // 9000
    $payments = [
        ['method' => 'cash', 'amount' => $totalExpected],
    ];

    /** @var Sale $sale */
    $sale = app(CompleteSale::class)->execute(
        user: $staff,
        items: $items,
        payments: $payments,
        idempotencyKey: (string) Str::uuid()
    );

    $lines = $sale->lines()->get();
    expect($lines)->toHaveCount(3);

    // Every line subtotal must be >= 0
    foreach ($lines as $line) {
        expect($line->subtotal)->toBeGreaterThanOrEqual(0);
    }

    // Line 1: 2 * 1000 = 2000
    $l1 = $lines->firstWhere('product_id', $p1->id);
    expect($l1->quantity)->toBe(2)
        ->and($l1->unit_price)->toBe(1000)
        ->and($l1->subtotal)->toBe(2000);

    // Line 2: 3 * 1500 = 4500
    $l2 = $lines->firstWhere('product_id', $p2->id);
    expect($l2->quantity)->toBe(3)
        ->and($l2->unit_price)->toBe(1500)
        ->and($l2->subtotal)->toBe(4500);

    // Line 3: 1 * 2500 = 2500
    $l3 = $lines->firstWhere('product_id', $p3->id);
    expect($l3->quantity)->toBe(1)
        ->and($l3->unit_price)->toBe(2500)
        ->and($l3->subtotal)->toBe(2500);

    // All lines sum to sale total
    expect($lines->sum('subtotal'))->toBe($sale->total)
        ->and($sale->total)->toBe($totalExpected);
});

test('(b) cart with one product split over 2 batches in the middle has correct subtotals', function () {
    $staff = ps();
    openShiftForSubtotalTest($staff);

    $p1 = createSubtotalTestProduct('Med A');
    $p2 = createSubtotalTestProduct('Med B');
    $p3 = createSubtotalTestProduct('Med C');

    createSubtotalTestBatch($p1, 'BA-1', 50, now()->addYear());
    // Product 2 split: Batch 1 has 2, Batch 2 has 10
    createSubtotalTestBatch($p2, 'BB-EARLY', 2, now()->addMonths(2));
    createSubtotalTestBatch($p2, 'BB-LATER', 10, now()->addMonths(6));
    createSubtotalTestBatch($p3, 'BC-1', 50, now()->addYear());

    setSubtotalTestPrice($p1, 1000); // 1000 santim
    setSubtotalTestPrice($p2, 2000); // 2000 santim
    setSubtotalTestPrice($p3, 500);  // 500 santim

    // Cart: p1 = 2 (2000), p2 = 5 (split 2 from BB-EARLY + 3 from BB-LATER = 10000), p3 = 4 (2000)
    // Total = 14000 santim
    $items = [
        ['product_id' => $p1->id, 'quantity' => 2],
        ['product_id' => $p2->id, 'quantity' => 5],
        ['product_id' => $p3->id, 'quantity' => 4],
    ];

    $totalExpected = (2 * 1000) + (5 * 2000) + (4 * 500); // 14000
    $payments = [
        ['method' => 'cash', 'amount' => $totalExpected],
    ];

    /** @var Sale $sale */
    $sale = app(CompleteSale::class)->execute(
        user: $staff,
        items: $items,
        payments: $payments,
        idempotencyKey: (string) Str::uuid()
    );

    $lines = $sale->lines()->get();
    // p1 = 1 line, p2 = 2 lines (split), p3 = 1 line -> Total 4 lines
    expect($lines)->toHaveCount(4);

    foreach ($lines as $line) {
        expect($line->subtotal)->toBeGreaterThanOrEqual(0);
    }

    // Product 2 lines must sum to quantity x unit_price = 5 * 2000 = 10000
    $p2Lines = $lines->where('product_id', $p2->id);
    expect($p2Lines)->toHaveCount(2)
        ->and($p2Lines->sum('quantity'))->toBe(5)
        ->and($p2Lines->sum('subtotal'))->toBe(10000);

    // Specifically: first split has qty 2, subtotal = 4000; second split has qty 3, subtotal = 6000
    $split1 = $p2Lines->firstWhere('quantity', 2);
    $split2 = $p2Lines->firstWhere('quantity', 3);
    expect($split1->subtotal)->toBe(4000)
        ->and($split2->subtotal)->toBe(6000);

    // Product 3 line: qty 4 * 500 = 2000
    $p3Line = $lines->firstWhere('product_id', $p3->id);
    expect($p3Line->subtotal)->toBe(2000);

    // All lines sum to sale total
    expect($lines->sum('subtotal'))->toBe($sale->total)
        ->and($sale->total)->toBe($totalExpected);
});

test('(c) cart with one product split over 3 batches has correct subtotals summing to total', function () {
    $staff = ps();
    openShiftForSubtotalTest($staff);

    $p1 = createSubtotalTestProduct('Multi Split Product');

    // 3 batches with small stock to force 3 splits:
    createSubtotalTestBatch($p1, 'MS-1', 1, now()->addMonths(2));
    createSubtotalTestBatch($p1, 'MS-2', 2, now()->addMonths(4));
    createSubtotalTestBatch($p1, 'MS-3', 10, now()->addMonths(6));

    setSubtotalTestPrice($p1, 1000); // 1000 santim

    // Request 6 units: 1 from MS-1, 2 from MS-2, 3 from MS-3
    // Line subtotal = 6 * 1000 = 6000 santim
    $items = [
        ['product_id' => $p1->id, 'quantity' => 6],
    ];

    $totalExpected = 6000;
    $payments = [
        ['method' => 'cash', 'amount' => $totalExpected],
    ];

    /** @var Sale $sale */
    $sale = app(CompleteSale::class)->execute(
        user: $staff,
        items: $items,
        payments: $payments,
        idempotencyKey: (string) Str::uuid()
    );

    $lines = $sale->lines()->get();
    expect($lines)->toHaveCount(3);

    foreach ($lines as $line) {
        expect($line->subtotal)->toBeGreaterThanOrEqual(0);
    }

    $b1Line = $lines->firstWhere('quantity', 1);
    $b2Line = $lines->firstWhere('quantity', 2);
    $b3Line = $lines->firstWhere('quantity', 3);

    expect($b1Line->subtotal)->toBe(1000)
        ->and($b2Line->subtotal)->toBe(2000)
        ->and($b3Line->subtotal)->toBe(3000);

    expect($lines->sum('subtotal'))->toBe($sale->total)
        ->and($sale->total)->toBe($totalExpected);
});

test('pms:verify-sale-lines command reports success when all sale lines match', function () {
    $this->artisan('pms:verify-sale-lines')
        ->expectsOutput('All sale lines match sale totals perfectly. No discrepancies found.')
        ->assertExitCode(0);
});

test('pms:verify-sale-lines command detects mismatched or negative line subtotals', function () {
    $staff = ps();
    openShiftForSubtotalTest($staff);

    $p1 = createSubtotalTestProduct('Product Mismatch');
    createSubtotalTestBatch($p1, 'B-MISMATCH', 10, now()->addYear());
    setSubtotalTestPrice($p1, 1000);

    // Create a normal sale
    $sale = app(CompleteSale::class)->execute(
        user: $staff,
        items: [['product_id' => $p1->id, 'quantity' => 2]],
        payments: [['method' => 'cash', 'amount' => 2000]],
        idempotencyKey: (string) Str::uuid()
    );

    // Corrupt one line subtotal to simulate legacy bad data
    $line = $sale->lines()->first();
    $line->update(['subtotal' => -500]);

    $this->artisan('pms:verify-sale-lines')
        ->expectsOutputToContain('Found 1 sale(s) with mismatched line subtotals:')
        ->assertExitCode(1);
});
