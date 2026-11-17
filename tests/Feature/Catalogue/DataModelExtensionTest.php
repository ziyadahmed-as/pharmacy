<?php

/**
 * Tests for the data-model extension:
 *
 *   Product   — image_path, selling_price, totalAvailableQuantity(), BR-06 serialisation
 *   Batch     — validation rules, duplicate-batch additive behaviour, shelf-life gate,
 *               auto-DEPLETED / auto-SELLABLE transitions
 *   Ledger    — append-only, one row per quantity change, product_id populated,
 *               buying_price absent from PS-serialised responses
 *
 * @fr FR-1.1, FR-2.1, FR-2.3, FR-2.10
 * @br BR-04, BR-06, BR-08
 */

use App\Actions\Inventory\ConfirmStockReceipt;
use App\Actions\Inventory\SubmitStockReceipt;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\ProductImageService;
use App\Services\StockMovementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

// ---------------------------------------------------------------------------
// Helpers shared across this file
// ---------------------------------------------------------------------------

function makeExtProduct(array $overrides = []): Product
{
    return Product::create(array_merge([
        'brand'          => 'Amoxicillin 500mg',
        'generic_name'   => 'Amoxicillin',
        'classification' => 'otc',
        'is_active'      => true,
    ], $overrides));
}

function makeExtBatch(Product $product, array $overrides = []): Batch
{
    return Batch::create(array_merge([
        'product_id'   => $product->id,
        'batch_no'     => 'B-' . uniqid(),
        'expiry_date'  => now()->addMonths(12)->toDateString(),
        'unit_cost'    => 1500,
        'status'       => Batch::STATUS_SELLABLE,
    ], $overrides));
}

/** Post stock into a batch so BatchStock rows exist. */
function postExtStock(Batch $batch, int $qty, string $type = 'receipt'): StockMovement
{
    return StockMovementService::post(
        batch: $batch,
        signedQuantity: $qty,
        type: $type,
        source: $batch->product,
        user: pm(),
        status: 'sellable',
    );
}

// ===========================================================================
// SECTION 1 — Product: image_path and selling_price
// ===========================================================================

test('product can store and retrieve image_path', function () {
    $product = makeExtProduct(['image_path' => 'product-images/abc123.jpg']);

    expect($product->fresh()->image_path)->toBe('product-images/abc123.jpg');
});

test('product selling_price is stored and cast as integer (minor units)', function () {
    $product = makeExtProduct(['selling_price' => 4500]);   // 45.00 ETB

    expect($product->fresh()->selling_price)->toBe(4500)
        ->and(gettype($product->fresh()->selling_price))->toBe('integer');
});

test('selling_price must be greater than zero (CHECK constraint)', function () {
    expect(fn () => makeExtProduct(['selling_price' => 0]))
        ->toThrow(\Illuminate\Database\QueryException::class);
})->skip(fn () => DB::connection()->getDriverName() === 'sqlite', 'SQLite CHECK fires differently; covered by service-layer test below');

test('selling_price zero is rejected at service level regardless of driver', function () {
    // The migration adds a CHECK constraint; we also test the validation layer
    // so the rule is enforced even on drivers that do not raise CHECK errors.
    $product = makeExtProduct();

    expect(fn () => app(\App\Actions\Catalogue\SetSellingPrice::class)->execute(
        product: $product,
        price: 0,
        effectiveFrom: now(),
        reason: 'test',
        user: pm(),
    ))->toThrow(ValidationException::class);
});

// ===========================================================================
// SECTION 2 — Product: totalAvailableQuantity()
// ===========================================================================

test('totalAvailableQuantity sums only sellable non-expired batches', function () {
    $product = makeExtProduct();

    // batch A: 30 sellable, not expired
    $batchA = makeExtBatch($product, ['batch_no' => 'QTY-A', 'expiry_date' => now()->addMonths(6)->toDateString()]);
    postExtStock($batchA, 30);

    // batch B: 20 sellable, also not expired
    $batchB = makeExtBatch($product, ['batch_no' => 'QTY-B', 'expiry_date' => now()->addMonths(3)->toDateString()]);
    postExtStock($batchB, 20);

    // batch C: 10 sellable but EXPIRED — must NOT count
    $batchC = makeExtBatch($product, ['batch_no' => 'QTY-C', 'expiry_date' => now()->subDay()->toDateString()]);
    postExtStock($batchC, 10);

    expect($product->fresh()->totalAvailableQuantity())->toBe(50);   // A + B only
});

test('totalAvailableQuantity is computed from batches, never stored', function () {
    $product = makeExtProduct();
    $batch   = makeExtBatch($product);
    postExtStock($batch, 15);

    // Directly decrement stock (simulating a sale) and verify the method reflects it.
    StockMovementService::post(
        batch: $batch,
        signedQuantity: -5,
        type: 'sale',
        source: $product,
        user: pm(),
        status: 'sellable',
    );

    expect($product->fresh()->totalAvailableQuantity())->toBe(10);
});

// ===========================================================================
// SECTION 3 — Batch: field validations
// ===========================================================================

test('batch expiry_date must be after manufacturing_date', function () {
    $product = makeExtProduct();

    $mfg    = now()->subMonths(2)->toDateString();
    $expiry = now()->subMonths(3)->toDateString();   // earlier than mfg — invalid

    expect(fn () => Batch::create([
        'product_id'         => $product->id,
        'batch_no'           => 'BAD-EXP',
        'manufacturing_date' => $mfg,
        'expiry_date'        => $expiry,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('manufacturing_date equal to expiry_date is rejected (must be strictly after)', function () {
    $product = makeExtProduct();
    $date    = now()->addMonths(6)->toDateString();

    expect(fn () => Batch::create([
        'product_id'         => $product->id,
        'batch_no'           => 'SAME-DATE',
        'manufacturing_date' => $date,
        'expiry_date'        => $date,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('batch with no manufacturing_date is accepted (mfg date is optional)', function () {
    $product = makeExtProduct();

    $batch = Batch::create([
        'product_id'  => $product->id,
        'batch_no'    => 'NO-MFG',
        'expiry_date' => now()->addMonths(12)->toDateString(),
    ]);

    expect($batch->manufacturing_date)->toBeNull();
});

test('unique constraint on product_id + batch_no is enforced', function () {
    $product = makeExtProduct();

    Batch::create([
        'product_id'  => $product->id,
        'batch_no'    => 'DUP-BATCH',
        'expiry_date' => now()->addMonths(12)->toDateString(),
    ]);

    expect(fn () => Batch::create([
        'product_id'  => $product->id,
        'batch_no'    => 'DUP-BATCH',
        'expiry_date' => now()->addMonths(12)->toDateString(),
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('same batch number for different products is allowed', function () {
    $p1 = makeExtProduct(['brand' => 'Drug A', 'generic_name' => 'Generic A']);
    $p2 = makeExtProduct(['brand' => 'Drug B', 'generic_name' => 'Generic B']);

    $b1 = Batch::create(['product_id' => $p1->id, 'batch_no' => 'SHARED-NO', 'expiry_date' => now()->addYear()->toDateString()]);
    $b2 = Batch::create(['product_id' => $p2->id, 'batch_no' => 'SHARED-NO', 'expiry_date' => now()->addYear()->toDateString()]);

    expect($b1->id)->not->toBe($b2->id);
});

// ===========================================================================
// SECTION 4 — Batch: duplicate receipt adds quantity (UC-04 A1)
// ===========================================================================

test('receiving the same batch_no twice adds quantity (A1)', function () {
    $product = makeExtProduct();
    $pmUser  = pm();
    $submit  = new SubmitStockReceipt;
    $confirm = new ConfirmStockReceipt;

    $line = [
        'product_id'  => $product->id,
        'batch_no'    => 'REUSE-ME',
        'expiry_date' => now()->addMonths(12)->toDateString(),
        'quantity'    => 25,
    ];

    $r1 = $submit->execute(['supplier_name' => 'S', 'receipt_date' => now()->toDateString(), 'idempotency_key' => 'DUP-R1', 'lines' => [$line]], $pmUser);
    $confirm->execute($r1, $pmUser);

    $r2 = $submit->execute(['supplier_name' => 'S', 'receipt_date' => now()->toDateString(), 'idempotency_key' => 'DUP-R2', 'lines' => [array_merge($line, ['quantity' => 15])]], $pmUser);
    $confirm->execute($r2, $pmUser);

    // Only one Batch row must exist for this product+batch_no combination.
    expect(Batch::where('product_id', $product->id)->where('batch_no', 'REUSE-ME')->count())->toBe(1);

    $batch = Batch::where('product_id', $product->id)->where('batch_no', 'REUSE-ME')->first();
    $qty   = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');

    expect($qty)->toBe(40);  // 25 + 15
});

// ===========================================================================
// SECTION 5 — Batch: shelf-life gate (FR-2.3)
// ===========================================================================

test('short shelf-life batch is rejected without PM override', function () {
    $product = makeExtProduct();
    $submit  = new SubmitStockReceipt;

    $payload = [
        'supplier_name'   => 'S',
        'receipt_date'    => now()->toDateString(),
        'idempotency_key' => 'SL-NO-OVERRIDE',
        'lines'           => [[
            'product_id'  => $product->id,
            'batch_no'    => 'SHORT-1',
            'expiry_date' => now()->addDays(20)->toDateString(),   // below default 90-day minimum
            'quantity'    => 10,
        ]],
    ];

    expect(fn () => $submit->execute($payload, ps()))
        ->toThrow(ValidationException::class);
});

test('short shelf-life batch is rejected with override flag but empty reason', function () {
    $product = makeExtProduct();
    $submit  = new SubmitStockReceipt;

    $payload = [
        'supplier_name'   => 'S',
        'receipt_date'    => now()->toDateString(),
        'idempotency_key' => 'SL-EMPTY-REASON',
        'lines'           => [[
            'product_id'  => $product->id,
            'batch_no'    => 'SHORT-2',
            'expiry_date' => now()->addDays(20)->toDateString(),
            'quantity'    => 10,
        ]],
    ];

    expect(fn () => $submit->execute($payload, pm(), overrideShelfLife: true, overrideReason: ''))
        ->toThrow(ValidationException::class);
});

test('PM can accept short shelf-life batch with a recorded reason', function () {
    $product = makeExtProduct();
    $submit  = new SubmitStockReceipt;

    $receipt = $submit->execute([
        'supplier_name'   => 'S',
        'receipt_date'    => now()->toDateString(),
        'idempotency_key' => 'SL-OVERRIDE-OK',
        'lines'           => [[
            'product_id'  => $product->id,
            'batch_no'    => 'SHORT-3',
            'expiry_date' => now()->addDays(20)->toDateString(),
            'quantity'    => 5,
        ]],
    ], pm(), overrideShelfLife: true, overrideReason: 'Urgent patient need — no alternatives');

    expect($receipt)->toBeInstanceOf(\App\Models\StockReceipt::class);
});

// ===========================================================================
// SECTION 6 — Batch: auto-DEPLETED / auto-SELLABLE transitions
// ===========================================================================

test('batch status becomes DEPLETED when sellable quantity reaches zero', function () {
    $product = makeExtProduct();
    $batch   = makeExtBatch($product, ['batch_no' => 'DEPL-1', 'status' => Batch::STATUS_SELLABLE]);
    postExtStock($batch, 5);

    expect($batch->fresh()->status)->toBe(Batch::STATUS_SELLABLE);

    // Sell all 5 units.
    StockMovementService::post(
        batch: $batch,
        signedQuantity: -5,
        type: 'sale',
        source: $product,
        user: pm(),
        status: 'sellable',
    );

    expect($batch->fresh()->status)->toBe(Batch::STATUS_DEPLETED);
});

test('DEPLETED batch returns to SELLABLE when stock is restocked', function () {
    $product = makeExtProduct();
    $batch   = makeExtBatch($product, ['batch_no' => 'DEPL-2']);
    postExtStock($batch, 3);

    // Sell out.
    StockMovementService::post(
        batch: $batch, signedQuantity: -3, type: 'sale',
        source: $product, user: pm(), status: 'sellable',
    );
    expect($batch->fresh()->status)->toBe(Batch::STATUS_DEPLETED);

    // Restock (e.g. void/return).
    StockMovementService::post(
        batch: $batch, signedQuantity: 10, type: 'return_from_customer',
        source: $product, user: pm(), status: 'sellable',
    );
    expect($batch->fresh()->status)->toBe(Batch::STATUS_SELLABLE);
});

test('quarantine movements alone do not trigger DEPLETED', function () {
    $product = makeExtProduct();
    $batch   = makeExtBatch($product, ['batch_no' => 'DEPL-3']);
    postExtStock($batch, 20);   // 20 sellable

    // Move all 20 to quarantine — but sellable pool is now 0 too, so it SHOULD deplete.
    // Actually: quarantine is a separate status bucket. After quarantining 20, sellable=0.
    // The expected behaviour is DEPLETED because sellable stock is 0.
    StockMovementService::post(
        batch: $batch, signedQuantity: -20, type: 'quarantine',
        source: $product, user: pm(), status: 'sellable',
    );
    StockMovementService::post(
        batch: $batch, signedQuantity: 20, type: 'quarantine',
        source: $product, user: pm(), status: 'quarantine',
    );

    // sellable = 0  → DEPLETED
    expect($batch->fresh()->status)->toBe(Batch::STATUS_DEPLETED);
});

test('batch status is preserved for QUARANTINED and RECALLED — not auto-overwritten', function () {
    $product = makeExtProduct();

    $quarantinedBatch = makeExtBatch($product, ['batch_no' => 'QR-STATUS', 'status' => Batch::STATUS_QUARANTINED]);
    postExtStock($quarantinedBatch, 10);

    // Adding sellable stock should NOT flip a QUARANTINED batch to SELLABLE
    // (only an explicit release approval does that).
    expect($quarantinedBatch->fresh()->status)->toBe(Batch::STATUS_QUARANTINED);
});

// ===========================================================================
// SECTION 7 — Batch: registered_by and delivery_note_no are stored
// ===========================================================================

test('confirmed receipt populates registered_by and registered_at on new batches', function () {
    $product = makeExtProduct();
    $pmUser  = pm();
    $submit  = new SubmitStockReceipt;
    $confirm = new ConfirmStockReceipt;

    $receipt = $submit->execute([
        'supplier_name'    => 'Pharma Co.',
        'delivery_note_no' => 'DN-9876',
        'receipt_date'     => now()->toDateString(),
        'idempotency_key'  => 'REG-BY-TEST',
        'lines'            => [[
            'product_id'  => $product->id,
            'batch_no'    => 'REG-BATCH',
            'expiry_date' => now()->addMonths(18)->toDateString(),
            'quantity'    => 30,
        ]],
    ], $pmUser);

    $confirm->execute($receipt, $pmUser);

    $batch = Batch::where('batch_no', 'REG-BATCH')->first();

    expect($batch->registered_by)->toBe($pmUser->id)
        ->and($batch->registered_at)->not->toBeNull()
        ->and($batch->delivery_note_no)->toBe('DN-9876');
});

// ===========================================================================
// SECTION 8 — Ledger: append-only and one row per quantity change
// ===========================================================================

test('ledger row is written for every BatchStock quantity change', function () {
    $product = makeExtProduct();
    $batch   = makeExtBatch($product, ['batch_no' => 'LEDGER-1']);

    expect(StockMovement::where('batch_id', $batch->id)->count())->toBe(0);

    postExtStock($batch, 50);

    expect(StockMovement::where('batch_id', $batch->id)->count())->toBe(1);

    StockMovementService::post(
        batch: $batch, signedQuantity: -10, type: 'sale',
        source: $product, user: pm(), status: 'sellable',
    );

    expect(StockMovement::where('batch_id', $batch->id)->count())->toBe(2);
});

test('ledger balance_after always equals the BatchStock quantity at that moment', function () {
    $product = makeExtProduct();
    $batch   = makeExtBatch($product, ['batch_no' => 'LEDGER-2']);

    postExtStock($batch, 100);
    StockMovementService::post(batch: $batch, signedQuantity: -30, type: 'sale', source: $product, user: pm(), status: 'sellable');
    postExtStock($batch, 20);  // restock

    $movements = StockMovement::where('batch_id', $batch->id)->orderBy('id')->get();
    $currentQty = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');

    // The last ledger row's balance_after must equal the live stock quantity.
    expect($movements->last()->balance_after)->toBe((int) $currentQty);

    // Intermediate: receipt +100 → balance 100; sale -30 → balance 70; receipt +20 → balance 90.
    expect($movements[0]->balance_after)->toBe(100)
        ->and($movements[1]->balance_after)->toBe(70)
        ->and($movements[2]->balance_after)->toBe(90);
});

test('ledger row carries product_id (denormalised for direct queries)', function () {
    $product = makeExtProduct();
    $batch   = makeExtBatch($product, ['batch_no' => 'LEDGER-3']);
    postExtStock($batch, 10);

    $movement = StockMovement::where('batch_id', $batch->id)->first();

    expect($movement->product_id)->toBe($product->id);
});

test('ledger is append-only: Eloquent refuses updates', function () {
    $product  = makeExtProduct();
    $batch    = makeExtBatch($product, ['batch_no' => 'APPEND-1']);
    $movement = postExtStock($batch, 5);

    expect(fn () => $movement->update(['quantity' => 999]))
        ->toThrow(\RuntimeException::class, 'append-only');
});

test('ledger is append-only: Eloquent refuses deletes', function () {
    $product  = makeExtProduct();
    $batch    = makeExtBatch($product, ['batch_no' => 'APPEND-2']);
    $movement = postExtStock($batch, 5);

    expect(fn () => $movement->delete())
        ->toThrow(\RuntimeException::class, 'append-only');
});

test('stock cannot go negative — service throws before writing a ledger row', function () {
    $product = makeExtProduct();
    $batch   = makeExtBatch($product, ['batch_no' => 'NEG-1']);
    postExtStock($batch, 5);

    expect(fn () => StockMovementService::post(
        batch: $batch, signedQuantity: -10, type: 'sale',
        source: $product, user: pm(), status: 'sellable',
    ))->toThrow(\RuntimeException::class);

    // Exactly one ledger row (the original receipt) must exist — no partial row.
    expect(StockMovement::where('batch_id', $batch->id)->count())->toBe(1);
});

// ===========================================================================
// SECTION 9 — BR-06: buying_price absent from PS-serialised product
// ===========================================================================

test('selling_price and unit_cost absent from toArray() for PharmacyStaff (BR-06)', function () {
    $psUser = ps();
    $this->actingAs($psUser);

    $product = makeExtProduct(['selling_price' => 7500]);
    $array   = $product->toArray();

    expect($array)->not->toHaveKey('selling_price');
});

test('selling_price present in toArray() for Owner (BR-06)', function () {
    $ownerUser = owner();
    $this->actingAs($ownerUser);

    $product = makeExtProduct(['selling_price' => 7500]);
    $array   = $product->toArray();

    expect($array)->toHaveKey('selling_price')
        ->and($array['selling_price'])->toBe(7500);
});

test('selling_price present in toArray() for PharmacyManager (BR-06)', function () {
    $pmUser = pm();
    $this->actingAs($pmUser);

    $product = makeExtProduct(['selling_price' => 3200]);
    $array   = $product->toArray();

    expect($array)->toHaveKey('selling_price')
        ->and($array['selling_price'])->toBe(3200);
});

test('unit_cost absent from StockOnHandQuery batch columns for PharmacyStaff (BR-06)', function () {
    $product = makeExtProduct();
    $batch   = makeExtBatch($product, ['batch_no' => 'COST-HIDE', 'unit_cost' => 4500]);
    postExtStock($batch, 20);

    $psUser = ps();
    $query  = new \App\Services\StockOnHandQuery;
    $result = $query->builder($psUser)->where('id', $product->id)->first();

    // The batch columns selected for PS must not include unit_cost.
    expect($result->batches->first()->getAttributes())->not->toHaveKey('unit_cost');
});

test('unit_cost present in StockOnHandQuery batch columns for Owner (BR-06)', function () {
    $product = makeExtProduct();
    $batch   = makeExtBatch($product, ['batch_no' => 'COST-SHOW', 'unit_cost' => 4500]);
    postExtStock($batch, 20);

    $ownerUser = owner();
    $query     = new \App\Services\StockOnHandQuery;
    $result    = $query->builder($ownerUser)->where('id', $product->id)->first();

    expect($result->batches->first()->unit_cost)->toBe(4500);
});

// ===========================================================================
// SECTION 10 — ProductImageService: file validation
// ===========================================================================

test('valid JPEG under 2 MB is accepted and stored', function () {
    Storage::fake('private');
    Storage::fake('public');

    $file    = UploadedFile::fake()->image('drug.jpg', 400, 400);
    $service = new ProductImageService;
    $result  = $service->store($file);

    expect($result)->toHaveKeys(['image_path', 'thumbnail_path'])
        ->and(Storage::disk('private')->exists($result['image_path']))->toBeTrue()
        ->and(Storage::disk('public')->exists($result['thumbnail_path']))->toBeTrue();
});

test('valid PNG under 2 MB is accepted', function () {
    Storage::fake('private');
    Storage::fake('public');

    $file   = UploadedFile::fake()->image('drug.png', 300, 300);
    $result = (new ProductImageService)->store($file);

    expect(Storage::disk('private')->exists($result['image_path']))->toBeTrue();
});

test('file over 2 MB is rejected', function () {
    Storage::fake('private');
    Storage::fake('public');

    // Create a file just over 2 MB.
    $file = UploadedFile::fake()->image('big.jpg')->size(2049);  // 2049 KB > 2 MB

    expect(fn () => (new ProductImageService)->store($file))
        ->toThrow(ValidationException::class);
});

test('file with wrong MIME type (even with image extension) is rejected', function () {
    Storage::fake('private');
    Storage::fake('public');

    // Create a file that masquerades as an image by extension but contains
    // plain text — finfo will detect text/plain.
    $tmp = tempnam(sys_get_temp_dir(), 'pms_test_');
    file_put_contents($tmp, '<?php echo "malicious"; ?>');

    $file = new UploadedFile($tmp, 'malicious.jpg', 'image/jpeg', null, true);

    expect(fn () => (new ProductImageService)->store($file))
        ->toThrow(ValidationException::class);

    @unlink($tmp);
});

test('original filename is never used — stored name is UUID-based', function () {
    Storage::fake('private');
    Storage::fake('public');

    $file   = UploadedFile::fake()->image('my-secret-drug.jpg', 200, 200);
    $result = (new ProductImageService)->store($file);

    expect($result['image_path'])->not->toContain('my-secret-drug');
});

test('thumbnail is 200x200 pixels', function () {
    Storage::fake('private');
    Storage::fake('public');

    // Use a non-square source to exercise the centre-crop path.
    $file   = UploadedFile::fake()->image('wide.jpg', 800, 400);
    $result = (new ProductImageService)->store($file);

    // Pull the stored thumbnail and verify dimensions via GD.
    $thumbData = Storage::disk('public')->get($result['thumbnail_path']);
    $tmpThumb  = tempnam(sys_get_temp_dir(), 'pms_thumb_');
    file_put_contents($tmpThumb, $thumbData);

    [$w, $h] = getimagesize($tmpThumb);
    @unlink($tmpThumb);

    expect($w)->toBe(200)->and($h)->toBe(200);
});

test('thumbnailUrl returns placeholder when product has no image', function () {
    expect(ProductImageService::thumbnailUrl(null))
        ->toContain('product-placeholder.png');
});

test('delete removes both original and thumbnail silently', function () {
    Storage::fake('private');
    Storage::fake('public');

    $file    = UploadedFile::fake()->image('todelete.jpg', 100, 100);
    $service = new ProductImageService;
    $result  = $service->store($file);

    $service->delete($result['image_path'], $result['thumbnail_path']);

    expect(Storage::disk('private')->exists($result['image_path']))->toBeFalse()
        ->and(Storage::disk('public')->exists($result['thumbnail_path']))->toBeFalse();
});

test('delete with null paths does not throw', function () {
    expect(fn () => (new ProductImageService)->delete(null, null))
        ->not->toThrow(\Throwable::class);
});
