<?php

/**
 * Tests for the nearest-expiry-first product sort (UC-13, UC-11).
 *
 * @fr FR-3.2, FR-2.10
 * @br BR-01, BR-02, BR-06
 * @nfr NFR-1 (< 1 s at 20 k products), NFR-6
 */

use App\Actions\Inventory\ConfirmStockReceipt;
use App\Actions\Inventory\SubmitStockReceipt;
use App\Actions\Sales\CompleteSale;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\NearestExpiryQuery;
use App\Services\StockMovementService;
use App\Services\StockOnHandQuery;
use Carbon\Carbon;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeNeProduct(array $overrides = []): Product
{
    return Product::create(array_merge([
        'brand'        => 'Test Product ' . uniqid(),
        'generic_name' => 'Generic',
        'classification'=> 'otc',
        'is_active'    => true,
    ], $overrides));
}

function makeNeBatch(Product $product, string $expiryDate, int $qty, array $extra = []): Batch
{
    $batch = Batch::create(array_merge([
        'product_id'  => $product->id,
        'batch_no'    => 'NE-' . uniqid(),
        'expiry_date' => $expiryDate,
        'unit_cost'   => 1000,
        'status'      => Batch::STATUS_SELLABLE,
    ], $extra));

    // Post stock into the batch (uses StockMovementService → BatchStock created).
    StockMovementService::post(
        batch: $batch,
        signedQuantity: $qty,
        type: 'receipt',
        source: $product,
        user: pm(),
        status: 'sellable',
    );

    return $batch->fresh();
}

// ===========================================================================
// SECTION 1 — Nearest expiry first
// ===========================================================================

test('products are sorted by nearest expiry first', function () {
    $farProduct  = makeNeProduct(['brand' => 'Far Expiry Product']);
    $nearProduct = makeNeProduct(['brand' => 'Near Expiry Product']);

    makeNeBatch($farProduct,  now()->addMonths(12)->toDateString(), 10);
    makeNeBatch($nearProduct, now()->addMonths(2)->toDateString(),  10);

    $results = (new NearestExpiryQuery)->builder(pm())->get();

    expect($results->first()->brand)->toBe('Near Expiry Product')
        ->and($results->last()->brand)->toBe('Far Expiry Product');
});

test('same expiry date ties broken by product name ascending', function () {
    $productZ = makeNeProduct(['brand' => 'Zinc Tablets']);
    $productA = makeNeProduct(['brand' => 'Amoxicillin']);
    $productM = makeNeProduct(['brand' => 'Metformin']);

    $sameExpiry = now()->addMonths(6)->toDateString();
    makeNeBatch($productZ, $sameExpiry, 5);
    makeNeBatch($productA, $sameExpiry, 5);
    makeNeBatch($productM, $sameExpiry, 5);

    $results = (new NearestExpiryQuery)->builder(pm())->pluck('brand')->all();

    // All have the same expiry, so they sort by brand ASC.
    expect($results)->toBe(['Amoxicillin', 'Metformin', 'Zinc Tablets']);
});

// ===========================================================================
// SECTION 2 — Expired batch excluded from sellable count and sort key
// ===========================================================================

test('expired batches are excluded from min_expiry and total_sellable', function () {
    $product = makeNeProduct(['brand' => 'Mixed Batches']);

    // Expired batch with stock — must NOT contribute to sellable count or min_expiry.
    makeNeBatch($product, now()->subDays(5)->toDateString(), 10);

    // Sellable batch expiring in 3 months.
    $expectedMin = now()->addMonths(3)->toDateString();
    makeNeBatch($product, $expectedMin, 20);

    $results = (new NearestExpiryQuery)->builder(pm())->where('id', $product->id)->first();

    // Only the non-expired batch counts.
    expect($results->total_sellable)->toBe(20)
        ->and(\Carbon\Carbon::parse($results->min_expiry)->toDateString())->toBe($expectedMin);
});

test('product whose only batch is expired appears as out-of-stock', function () {
    $product = makeNeProduct(['brand' => 'All Expired']);

    makeNeBatch($product, now()->subDays(10)->toDateString(), 30);

    $result = (new NearestExpiryQuery)->builder(pm())->where('id', $product->id)->first();

    expect($result->total_sellable)->toBe(0)
        ->and($result->min_expiry)->toBeNull();
});

// ===========================================================================
// SECTION 3 — Out-of-stock products go to the bottom
// ===========================================================================

test('out-of-stock products are sorted to the bottom', function () {
    $inStock   = makeNeProduct(['brand' => 'In Stock']);
    $noStock   = makeNeProduct(['brand' => 'No Stock']);

    makeNeBatch($inStock, now()->addMonths(6)->toDateString(), 10);
    // $noStock has no batches at all.

    $results = (new NearestExpiryQuery)->builder(pm())->get();

    expect($results->first()->brand)->toBe('In Stock')
        ->and($results->last()->brand)->toBe('No Stock')
        ->and($results->last()->total_sellable)->toBe(0);
});

test('sold-out product moves to bottom immediately after sale', function () {
    $product = makeNeProduct(['brand' => 'Sells Out']);
    makeNeBatch($product, now()->addMonths(6)->toDateString(), 5);

    // Verify it's at the top before the sale.
    $before = (new NearestExpiryQuery)->builder(pm())->where('id', $product->id)->first();
    expect($before->total_sellable)->toBe(5);

    // Sell all 5 units.
    $batch = Batch::where('product_id', $product->id)->first();
    StockMovementService::post(
        batch: $batch,
        signedQuantity: -5,
        type: 'sale',
        source: $product,
        user: pm(),
        status: 'sellable',
    );

    // Re-query: the product must now show 0 stock and be sorted to the bottom.
    $after = (new NearestExpiryQuery)->builder(pm())->where('id', $product->id)->first();
    expect($after->total_sellable)->toBe(0)
        ->and($after->min_expiry)->toBeNull();
});

// ===========================================================================
// SECTION 4 — Multi-batch product uses earliest sellable batch for sort key
// ===========================================================================

test('multi-batch product uses its earliest sellable batch as min_expiry', function () {
    $product = makeNeProduct(['brand' => 'Multi Batch']);

    // Three batches with different expiry dates.
    makeNeBatch($product, now()->addMonths(10)->toDateString(), 20);
    $expectedMin = now()->addMonths(3)->toDateString();
    makeNeBatch($product, $expectedMin,  15);
    makeNeBatch($product, now()->addMonths(6)->toDateString(),  10);

    $result = (new NearestExpiryQuery)->builder(pm())->where('id', $product->id)->first();

    // min_expiry must be the earliest (3 months from now).
    expect(\Carbon\Carbon::parse($result->min_expiry)->toDateString())->toBe($expectedMin)
        ->and($result->total_sellable)->toBe(45);  // 20 + 15 + 10
});

test('multi-batch product where earliest batch is expired uses next sellable batch', function () {
    $product = makeNeProduct(['brand' => 'Earliest Expired']);

    makeNeBatch($product, now()->subDays(5)->toDateString(),   10);  // expired — excluded
    makeNeBatch($product, now()->addMonths(8)->toDateString(),  20);
    $expectedMin = now()->addMonths(2)->toDateString();
    makeNeBatch($product, $expectedMin,  15);

    $result = (new NearestExpiryQuery)->builder(pm())->where('id', $product->id)->first();

    // The expired batch is excluded; min_expiry = the next earliest (2 months).
    expect(\Carbon\Carbon::parse($result->min_expiry)->toDateString())->toBe($expectedMin)
        ->and($result->total_sellable)->toBe(35);  // 20 + 15 (expired excluded)
});

// ===========================================================================
// SECTION 5 — Quantity drops after a sale
// ===========================================================================

test('total_sellable reflects reduced quantity after a sale via CompleteSale', function () {
    $pmUser = pm();   // reuse the SAME PM user for shift and sale

    $product = makeNeProduct(['brand' => 'Sale Quantity Drop']);

    // Set up a product price so CompleteSale can resolve it.
    \App\Models\ProductPrice::create([
        'product_id'    => $product->id,
        'unit_id'       => null,
        'price'         => 500,   // 5.00 ETB
        'effective_from' => now(),
        'reason'        => 'Test price',
        'set_by'        => $pmUser->id,
        'created_at'    => now(),
    ]);

    // Create a shift so the sale can complete.
    $till  = \App\Models\Till::create(['name' => 'TEST-TILL', 'is_active' => true]);
    $shift = \App\Models\Shift::create([
        'till_id'      => $till->id,
        'user_id'      => $pmUser->id,
        'opened_at'    => now(),
        'opening_float'=> 1000,
        'status'       => 'open',
    ]);

    makeNeBatch($product, now()->addMonths(6)->toDateString(), 50);

    // Verify quantity before sale.
    $before = (new NearestExpiryQuery)->builder($pmUser)->where('id', $product->id)->first();
    expect($before->total_sellable)->toBe(50);

    // Complete a sale of 10 units.
    $sale = (new CompleteSale)->execute(
        user: $pmUser,
        items: [[
            'product_id' => $product->id,
            'quantity'   => 10,
            'unit_id'    => null,
            'batch_id'   => null,
        ]],
        payments: [['method' => 'cash', 'amount' => 5000, 'reference' => '']],
        idempotencyKey: 'NEAR-EXPIRY-SALE-1',
    );

    // Re-query: quantity must be 40.
    $after = (new NearestExpiryQuery)->builder($pmUser)->where('id', $product->id)->first();
    expect($after->total_sellable)->toBe(40);
});

// ===========================================================================
// SECTION 6 — PP response contains no cost fields
// ===========================================================================

test('NearestExpiryQuery does not expose unit_cost or buying price for PP', function () {
    $product = makeNeProduct(['brand' => 'Cost Hidden']);
    makeNeBatch($product, now()->addMonths(6)->toDateString(), 10, ['unit_cost' => 9999]);

    $psUser = ps();
    $result = (new NearestExpiryQuery)->builder($psUser)->where('id', $product->id)->first();

    // current_price must be NULL (or absent) for PP — never the cost.
    $attributes = $result->getAttributes();
    expect($attributes)->not->toHaveKey('unit_cost')
        ->and($attributes['current_price'] ?? null)->toBeNull();
});

test('NearestExpiryQuery exposes current_price for Owner', function () {
    $product = makeNeProduct(['brand' => 'Cost Visible']);
    makeNeBatch($product, now()->addMonths(6)->toDateString(), 10);

    // Set a selling price.
    \App\Models\ProductPrice::create([
        'product_id'    => $product->id,
        'unit_id'       => null,
        'price'         => 750,
        'effective_from' => now(),
        'reason'        => 'Test',
        'set_by'        => owner()->id,
        'created_at'    => now(),
    ]);

    $result = (new NearestExpiryQuery)->builder(owner())->where('id', $product->id)->first();

    expect($result->current_price)->toBe(750);
});

test('toPosRow DTO never contains unit_cost or buying_price', function () {
    $product = makeNeProduct(['brand' => 'DTO Check']);
    makeNeBatch($product, now()->addMonths(6)->toDateString(), 5, ['unit_cost' => 3000]);

    \App\Models\ProductPrice::create([
        'product_id'    => $product->id,
        'unit_id'       => null,
        'price'         => 800,
        'effective_from' => now(),
        'reason'        => 'Test',
        'set_by'        => pm()->id,
        'created_at'    => now(),
    ]);

    $row = (new NearestExpiryQuery)->builder(pm())
        ->with('latestPrice')
        ->where('id', $product->id)
        ->first();

    $dto = (new NearestExpiryQuery)->toPosRow($row);

    // Must have selling price but never cost.
    expect($dto)->toHaveKey('price_santim')
        ->and($dto['price_santim'])->toBe(800)
        ->and($dto)->not->toHaveKey('unit_cost')
        ->and($dto)->not->toHaveKey('buying_price')
        ->and($dto)->not->toHaveKey('cost');
});

// ===========================================================================
// SECTION 7 — Search preserves nearest-expiry ordering
// ===========================================================================

test('search by brand preserves nearest-expiry-first ordering', function () {
    $p1 = makeNeProduct(['brand' => 'Aspirin 100mg']);
    $p2 = makeNeProduct(['brand' => 'Aspirin 500mg']);
    $p3 = makeNeProduct(['brand' => 'Ibuprofen 200mg']);

    makeNeBatch($p1, now()->addMonths(9)->toDateString(), 10);
    makeNeBatch($p2, now()->addMonths(1)->toDateString(),  10);
    makeNeBatch($p3, now()->addMonths(6)->toDateString(),  10);

    // Search for "Aspirin" — only p1 and p2 should match.
    $results = (new NearestExpiryQuery(['search' => 'Aspirin']))->builder(pm())->get();

    expect($results)->toHaveCount(2)
        ->and($results->first()->brand)->toBe('Aspirin 500mg')  // nearer expiry first
        ->and($results->last()->brand)->toBe('Aspirin 100mg');
});

test('search by generic_name preserves ordering', function () {
    $p1 = makeNeProduct(['brand' => 'Drug A', 'generic_name' => 'Paracetamol']);
    $p2 = makeNeProduct(['brand' => 'Drug B', 'generic_name' => 'Paracetamol']);

    makeNeBatch($p1, now()->addMonths(8)->toDateString(), 5);
    makeNeBatch($p2, now()->addMonths(2)->toDateString(), 5);

    $results = (new NearestExpiryQuery(['search' => 'Paracetamol']))->builder(pm())->get();

    expect($results)->toHaveCount(2)
        ->and($results->first()->brand)->toBe('Drug B')  // nearer expiry
        ->and($results->last()->brand)->toBe('Drug A');
});

test('search by barcode finds exactly one product', function () {
    $p1 = makeNeProduct(['brand' => 'Barcode Product', 'barcode' => 'BAR123456']);
    makeNeBatch($p1, now()->addMonths(4)->toDateString(), 8);

    $results = (new NearestExpiryQuery(['search' => 'BAR123456']))->builder(pm())->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->brand)->toBe('Barcode Product');
});

// ===========================================================================
// SECTION 8 — Pagination
// ===========================================================================

test('NearestExpiryQuery supports pagination', function () {
    // Create 25 products with stock.
    for ($i = 0; $i < 25; $i++) {
        $p = makeNeProduct(['brand' => "Paginated {$i}"]);
        makeNeBatch($p, now()->addMonths($i + 1)->toDateString(), 5);
    }

    $page1 = (new NearestExpiryQuery)->builder(pm())->paginate(10, ['*'], 'page', 1);
    $page2 = (new NearestExpiryQuery)->builder(pm())->paginate(10, ['*'], 'page', 2);

    expect($page1->count())->toBe(10)
        ->and($page2->count())->toBe(10)
        ->and($page1->lastPage())->toBe(3);  // 25 / 10 = 3 pages
});

// ===========================================================================
// SECTION 9 — StockOnHandQuery uses the same nearest-expiry sort
// ===========================================================================

test('StockOnHandQuery also sorts by nearest expiry first', function () {
    $far  = makeNeProduct(['brand' => 'ZZ Far Expiry']);
    $near = makeNeProduct(['brand' => 'AA Near Expiry']);

    makeNeBatch($far,  now()->addMonths(12)->toDateString(), 10);
    makeNeBatch($near, now()->addMonths(1)->toDateString(),  10);

    $results = (new StockOnHandQuery)->builder(pm())->get();

    // Despite "AA" < "ZZ" alphabetically, the near-expiry product must come first.
    expect($results->first()->brand)->toBe('AA Near Expiry')
        ->and($results->last()->brand)->toBe('ZZ Far Expiry');
});

// ===========================================================================
// SECTION 10 — Recalled batches excluded
// ===========================================================================

test('recalled batches are excluded from min_expiry and total_sellable', function () {
    $product = makeNeProduct(['brand' => 'Recalled Test']);

    $recalledBatch = makeNeBatch($product, now()->addMonths(2)->toDateString(), 15);
    $expectedMin = now()->addMonths(8)->toDateString();
    makeNeBatch($product, $expectedMin, 20);

    // Mark the first batch as recalled via quarantine_record.
    \App\Models\QuarantineRecord::create([
        'batch_id'   => $recalledBatch->id,
        'quantity'   => 15,
        'reason'     => 'recalled',
        'status'     => 'open',
        'created_by' => pm()->id,
    ]);

    $result = (new NearestExpiryQuery)->builder(pm())->where('id', $product->id)->first();

    // Recalled batch excluded: min_expiry = 8 months (not 2), qty = 20 (not 35).
    expect(\Carbon\Carbon::parse($result->min_expiry)->toDateString())->toBe($expectedMin)
        ->and($result->total_sellable)->toBe(20);
});

// ===========================================================================
// SECTION 11 — Quarantined stock excluded from sellable count
// ===========================================================================

test('quarantined batch stock is excluded from total_sellable', function () {
    $product = makeNeProduct(['brand' => 'Quarantine Test']);

    $batch = makeNeBatch($product, now()->addMonths(6)->toDateString(), 30);

    // Move 20 units to quarantine (deduct from sellable, add to quarantine).
    StockMovementService::post(
        batch: $batch,
        signedQuantity: -20,
        type: 'quarantine',
        source: $product,
        user: pm(),
        status: 'sellable',
    );
    StockMovementService::post(
        batch: $batch,
        signedQuantity: 20,
        type: 'quarantine',
        source: $product,
        user: pm(),
        status: 'quarantine',
    );

    $result = (new NearestExpiryQuery)->builder(pm())->where('id', $product->id)->first();

    // Only 10 sellable (30 - 20 quarantined).
    expect($result->total_sellable)->toBe(10);
});

// ===========================================================================
// SECTION 12 — Expiry colour coding
// ===========================================================================

test('expiryColour returns green when days_left > 90', function () {
    expect(NearestExpiryQuery::expiryColour(120))->toBe('green');
});

test('expiryColour returns yellow when days_left between 61 and 90', function () {
    expect(NearestExpiryQuery::expiryColour(75))->toBe('yellow');
});

test('expiryColour returns orange when days_left between 31 and 60', function () {
    expect(NearestExpiryQuery::expiryColour(45))->toBe('orange');
});

test('expiryColour returns red when days_left <= 30', function () {
    expect(NearestExpiryQuery::expiryColour(15))->toBe('red')
        ->and(NearestExpiryQuery::expiryColour(0))->toBe('red');
});

test('expiryColour returns slate when days_left is null', function () {
    expect(NearestExpiryQuery::expiryColour(null))->toBe('slate');
});

// ===========================================================================
// SECTION 13 — Performance smoke test (NFR-1, NFR-6)
// ===========================================================================

test('query stays fast with many products (smoke test)', function () {
    // Create 200 products with stock — enough to prove the SQL approach scales.
    // (20k products would take too long to seed in a unit test; 200 proves the
    // SQL pattern and index usage; a full 20k benchmark is a separate load test.)
    for ($i = 0; $i < 200; $i++) {
        $p = makeNeProduct(['brand' => "Perf {$i}"]);
        makeNeBatch($p, now()->addDays(rand(1, 365))->toDateString(), rand(1, 100));
    }

    $start = microtime(true);
    $page  = (new NearestExpiryQuery)->builder(pm())->paginate(20);
    $elapsed = microtime(true) - $start;

    expect($page->count())->toBe(20)
        ->and($elapsed)->toBeLessThan(2.0);  // generous for SQLite in-memory
});
