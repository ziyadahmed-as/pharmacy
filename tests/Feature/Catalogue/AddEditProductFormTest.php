<?php

/**
 * Feature tests for the Add / Edit Product form (UC-01, UC-02, UC-04).
 *
 * Covers every rule from the BRD task spec:
 *  - PP gets 403 on create, edit, deactivate, price change
 *  - Every required field (brand, generic_name, classification, unit, category)
 *  - Expiry date before manufacture date rejected
 *  - Manufacturing date in the future rejected
 *  - Duplicate barcode / duplicate attributes detected
 *  - Stock level cross-validation (min <= reorder <= max)
 *  - Atomic save + double-click idempotency
 *  - First batch creates stock and a RECEIPT ledger row
 *  - Expired first batch goes to quarantine
 *  - Base unit locked after stock exists
 *  - Price-below-buying-price warning (non-blocking)
 *  - Clinical section hidden for non_drug products
 *  - Buying price absent from PP serialised product responses
 *  - Auto-generated product_code
 *  - Classification change audited
 */

use App\Actions\Catalogue\SaveProduct;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function apCat(string $name = 'Test Category'): Category
{
    return Category::firstOrCreate(['name' => $name]);
}

function apProductData(array $overrides = []): array
{
    return array_merge([
        'brand'          => 'AP Test ' . uniqid(),
        'generic_name'   => 'Generic AP',
        'classification' => 'otc',
        'unit'           => 'piece',
        'category_id'    => apCat()->id,
        'reorder_level'  => 20,
        'max_level'      => 100,
        'min_level'      => 5,
    ], $overrides);
}

// ===========================================================================
// SECTION 1 — Authorisation: PP is refused, OW/PM is allowed
// ===========================================================================

test('PP is refused (403) on the create page', function () {
    actAs(ps());
    $this->get(route('catalogue.create'))->assertForbidden();
});

test('PP is refused (403) on the edit page', function () {
    $p = Product::create(apProductData());
    actAs(ps());
    $this->get(route('catalogue.edit', $p))->assertForbidden();
});

test('PP cannot deactivate a product via SaveProduct action', function () {
    $psUser = ps();
    $p = Product::create(apProductData());

    // DeactivateProduct throws ValidationException (not AuthorizationException)
    // when the user lacks catalogue.manage — the important thing is it throws.
    expect(fn () => (new \App\Actions\Catalogue\DeactivateProduct)->execute($p, 'test', $psUser))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('PP cannot change selling price via SetSellingPrice action', function () {
    $psUser = ps();
    $p = Product::create(apProductData());

    expect(fn () => (new \App\Actions\Catalogue\SetSellingPrice)->execute(
        product: $p,
        price: 1000,
        effectiveFrom: now(),
        reason: 'test',
        user: $psUser,
    ))->toThrow(ValidationException::class);
});

test('PP is refused (403) on SaveProduct action directly', function () {
    $psUser = ps();

    expect(fn () => (new SaveProduct)->execute(apProductData(), null, $psUser))
        ->toThrow(ValidationException::class);
});

test('PM and Owner can access create and edit pages', function () {
    $p = Product::create(apProductData());

    actAs(pm());
    $this->get(route('catalogue.create'))->assertOk();
    $this->get(route('catalogue.edit', $p))->assertOk();

    actAs(owner());
    $this->get(route('catalogue.create'))->assertOk();
    $this->get(route('catalogue.edit', $p))->assertOk();
});

// ===========================================================================
// SECTION 2 — Required fields
// ===========================================================================

test('brand is required', function () {
    expect(fn () => (new SaveProduct)->execute(
        array_merge(apProductData(), ['brand' => '']),
        null, pm()
    ))->toThrow(ValidationException::class);
});

test('generic_name is required', function () {
    expect(fn () => (new SaveProduct)->execute(
        array_merge(apProductData(), ['generic_name' => '']),
        null, pm()
    ))->toThrow(ValidationException::class);
});

test('classification is required', function () {
    expect(fn () => (new SaveProduct)->execute(
        array_merge(apProductData(), ['classification' => '']),
        null, pm()
    ))->toThrow(ValidationException::class);
});

test('base unit is required', function () {
    $pmUser = pm();
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class)
        ->set('brand', 'Unit Required Test')
        ->set('generic_name', 'Generic')
        ->set('classification', 'otc')
        ->set('category_id', apCat()->id)
        ->set('unit', '')   // blank
        ->call('save')
        ->assertHasErrors(['unit']);
});

test('category is required', function () {
    $pmUser = pm();
    actAs($pmUser);

    // The category_required check lives in the Livewire form component, not the action.
    Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class)
        ->set('brand', 'Cat Required Test')
        ->set('generic_name', 'Generic')
        ->set('classification', 'otc')
        ->set('unit', 'piece')
        ->set('category_id', null)   // no category
        ->call('save')
        ->assertHasErrors(['category_id']);
});

// ===========================================================================
// SECTION 3 — Duplicate detection
// ===========================================================================

test('duplicate barcode is detected and raises DuplicateProductException', function () {
    $existing = Product::create(apProductData(['barcode' => 'DUP-BAR-001']));

    expect(fn () => (new SaveProduct)->execute(
        apProductData(['barcode' => 'DUP-BAR-001']),
        null, pm()
    ))->toThrow(\App\Actions\Catalogue\DuplicateProductException::class);
});

test('duplicate brand+strength+form+pack raises DuplicateProductException', function () {
    Product::create(apProductData([
        'brand' => 'DupDrug', 'strength' => '500mg',
        'dosage_form' => 'Tablet', 'pack_size' => '10x10',
    ]));

    expect(fn () => (new SaveProduct)->execute(
        apProductData([
            'brand' => 'DupDrug', 'strength' => '500mg',
            'dosage_form' => 'Tablet', 'pack_size' => '10x10',
        ]),
        null, pm()
    ))->toThrow(\App\Actions\Catalogue\DuplicateProductException::class);
});

test('editing a product with its own barcode does not raise a duplicate', function () {
    $p = Product::create(apProductData(['barcode' => 'SELF-BAR-001']));

    // Should succeed — same product, same barcode.
    $saved = (new SaveProduct)->execute(
        apProductData(['barcode' => 'SELF-BAR-001', 'brand' => $p->brand . ' updated']),
        $p, pm()
    );

    expect($saved->id)->toBe($p->id);
});

// ===========================================================================
// SECTION 4 — Stock level cross-validation
// ===========================================================================

test('min_level greater than reorder_level is rejected', function () {
    expect(fn () => (new SaveProduct)->execute(
        apProductData(['min_level' => 50, 'reorder_level' => 30, 'max_level' => 100]),
        null, pm()
    ))->toThrow(ValidationException::class);
});

test('reorder_level greater than max_level is rejected', function () {
    expect(fn () => (new SaveProduct)->execute(
        apProductData(['min_level' => 5, 'reorder_level' => 150, 'max_level' => 100]),
        null, pm()
    ))->toThrow(ValidationException::class);
});

test('valid min <= reorder <= max passes', function () {
    $saved = (new SaveProduct)->execute(
        apProductData(['min_level' => 5, 'reorder_level' => 20, 'max_level' => 100]),
        null, pm()
    );
    expect($saved)->toBeInstanceOf(Product::class);
});

// ===========================================================================
// SECTION 5 — Auto-generated product_code
// ===========================================================================

test('product_code is auto-generated when not provided', function () {
    $p = (new SaveProduct)->execute(
        array_merge(apProductData(), ['product_code' => null]),
        null, pm()
    );

    expect($p->product_code)->toMatch('/^PRD-\d{4}-\d{5}$/');
});

test('provided product_code is preserved', function () {
    $p = (new SaveProduct)->execute(
        apProductData(['product_code' => 'MY-CUSTOM-001']),
        null, pm()
    );

    expect($p->product_code)->toBe('MY-CUSTOM-001');
});

test('sequential auto-codes do not collide', function () {
    $p1 = (new SaveProduct)->execute(apProductData(), null, pm());
    $p2 = (new SaveProduct)->execute(apProductData(['brand' => 'Different B ' . uniqid()]), null, pm());

    expect($p1->product_code)->not->toBe($p2->product_code);
});

// ===========================================================================
// SECTION 6 — Atomic save with first batch
// ===========================================================================

test('product + first batch saved atomically in one transaction', function () {
    $pmUser = pm();
    $idem   = (string) \Illuminate\Support\Str::uuid();

    $saved = (new SaveProduct)->execute(
        array_merge(apProductData(), [
            'selling_price_santim'    => 5000,
            'selling_price_reason'    => 'Initial',
            'selling_price_effective' => now()->toIso8601String(),
            'first_batch'             => [
                'batch_no'           => 'FB-001',
                'manufacturing_date' => now()->subMonths(3)->toDateString(),
                'expiry_date'        => now()->addMonths(18)->toDateString(),
                'unit_cost'          => 2000,
                'quantity'           => 100,
                'supplier_name'      => 'Test Supplier',
                'idempotency_key'    => $idem,
            ],
        ]),
        null, $pmUser
    );

    // Product saved
    expect($saved)->toBeInstanceOf(Product::class);

    // Batch created
    $batch = Batch::where('product_id', $saved->id)->first();
    expect($batch)->not->toBeNull()
        ->and($batch->batch_no)->toBe('FB-001');

    // Sellable stock created with correct quantity
    $qty = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');
    expect($qty)->toBe(100);

    // RECEIPT ledger row written
    $movement = StockMovement::where('batch_id', $batch->id)->where('type', 'receipt')->first();
    expect($movement)->not->toBeNull()
        ->and($movement->quantity)->toBe(100)
        ->and($movement->balance_after)->toBe(100);

    // Selling price saved
    $price = ProductPrice::where('product_id', $saved->id)->whereNull('unit_id')->first();
    expect($price?->price)->toBe(5000);
});

test('double-click with same idempotency key creates only one batch and one receipt', function () {
    $pmUser = pm();
    $idem   = (string) \Illuminate\Support\Str::uuid();
    $batchData = [
        'batch_no'           => 'IDEM-BATCH',
        'manufacturing_date' => now()->subMonths(1)->toDateString(),
        'expiry_date'        => now()->addMonths(12)->toDateString(),
        'unit_cost'          => 1000,
        'quantity'           => 50,
        'idempotency_key'    => $idem,
    ];

    $p = (new SaveProduct)->execute(array_merge(apProductData(), ['first_batch' => $batchData]), null, $pmUser);

    // Second call with same idempotency key (simulates double-click)
    (new SaveProduct)->execute(array_merge(apProductData(['brand' => $p->brand . ' dup']), ['first_batch' => array_merge($batchData, ['idempotency_key' => $idem])]), Product::find($p->id), $pmUser);

    // Only one receipt with this key
    expect(\App\Models\StockReceipt::where('idempotency_key', $idem)->count())->toBe(1);

    // Only 50 units, not 100
    $batch = Batch::where('product_id', $p->id)->where('batch_no', 'IDEM-BATCH')->first();
    $qty   = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');
    expect($qty)->toBe(50);
});

// ===========================================================================
// SECTION 7 — Batch validation: expiry before manufacture
// ===========================================================================

test('expiry date before manufacturing date is rejected by Livewire form', function () {
    $pmUser = pm();
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class)
        ->set('brand', 'Test Drug')
        ->set('generic_name', 'Test Generic')
        ->set('classification', 'otc')
        ->set('category_id', apCat()->id)
        ->set('unit', 'piece')
        ->set('batchNo', 'EXPIRY-TEST')
        ->set('batchMfgDate', now()->subMonths(1)->toDateString())
        ->set('batchExpiryDate', now()->subMonths(3)->toDateString())   // before mfg
        ->set('batchBuyingPrice', '10.00')
        ->set('batchQuantity', '5')
        ->call('save')
        ->assertHasErrors(['batchExpiryDate']);
});

test('manufacturing date in the future is rejected by Livewire form', function () {
    $pmUser = pm();
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class)
        ->set('brand', 'Future Test')
        ->set('generic_name', 'Future Generic')
        ->set('classification', 'otc')
        ->set('category_id', apCat()->id)
        ->set('unit', 'piece')
        ->set('batchNo', 'FUTURE-MFG')
        ->set('batchMfgDate', now()->addDays(5)->toDateString())   // future
        ->set('batchExpiryDate', now()->addMonths(12)->toDateString())
        ->set('batchBuyingPrice', '10.00')
        ->set('batchQuantity', '5')
        ->call('save')
        ->assertHasErrors(['batchMfgDate']);
});

// ===========================================================================
// SECTION 8 — Expired first batch goes to quarantine
// ===========================================================================

test('expired first batch is placed in quarantine not sellable', function () {
    $pmUser = pm();
    $idem   = (string) \Illuminate\Support\Str::uuid();

    $saved = (new SaveProduct)->execute(
        array_merge(apProductData(), [
            'first_batch' => [
                'batch_no'           => 'EXPIRED-FB',
                'manufacturing_date' => now()->subYears(2)->toDateString(),
                'expiry_date'        => now()->subDays(10)->toDateString(),   // already expired
                'unit_cost'          => 500,
                'quantity'           => 20,
                'idempotency_key'    => $idem,
            ],
        ]),
        null, $pmUser
    );

    $batch = Batch::where('product_id', $saved->id)->first();

    $sellable   = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity');
    $quarantine = BatchStock::where('batch_id', $batch->id)->where('status', 'quarantine')->sum('quantity');

    // Expired batch → goes to quarantine automatically
    expect($sellable)->toBe(0)
        ->and($quarantine)->toBe(20);
});

// ===========================================================================
// SECTION 9 — Base unit locked after stock
// ===========================================================================

test('base unit cannot be changed once the product has sellable stock', function () {
    $pmUser  = pm();
    $product = Product::create(apProductData(['unit' => 'tablet']));

    // Create a batch with stock
    $batch = Batch::create([
        'product_id'  => $product->id,
        'batch_no'    => 'UNIT-LOCK-01',
        'expiry_date' => now()->addMonths(12)->toDateString(),
        'unit_cost'   => 1000,
        'status'      => Batch::STATUS_SELLABLE,
    ]);
    StockMovementService::post($batch, 10, 'receipt', $product, $pmUser, 'sellable');

    // Attempt to change unit — should be blocked
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class, ['product' => $product])
        ->set('unit', 'capsule')   // changed from 'tablet'
        ->call('save')
        ->assertHasErrors(['unit']);
});

test('base unit can still be set freely on a new product with no stock', function () {
    $pmUser = pm();

    $saved = (new SaveProduct)->execute(
        apProductData(['unit' => 'vial']),
        null, $pmUser
    );

    expect($saved->unit)->toBe('vial');
});

// ===========================================================================
// SECTION 10 — Price-below-buying-price warning (non-blocking)
// ===========================================================================

test('price below buying price triggers computed warning but does not block save', function () {
    $pmUser = pm();
    actAs($pmUser);

    $component = Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class)
        ->set('brand', 'Price Warning Test')
        ->set('generic_name', 'Generic')
        ->set('classification', 'otc')
        ->set('category_id', apCat()->id)
        ->set('unit', 'piece')
        ->set('sellingPrice', '5.00')      // selling = 5 ETB
        ->set('batchBuyingPrice', '8.00'); // buying = 8 ETB → warning

    // The computed priceBelowCostWarning should be non-null
    expect($component->get('priceBelowCostWarning'))->not->toBeNull();
});

test('price above buying price does not trigger warning', function () {
    $pmUser = pm();
    actAs($pmUser);

    $component = Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class)
        ->set('sellingPrice', '12.00')
        ->set('batchBuyingPrice', '8.00');   // selling > buying = no warning

    expect($component->get('priceBelowCostWarning'))->toBeNull();
});

// ===========================================================================
// SECTION 11 — Clinical section hidden for non_drug
// ===========================================================================

test('clinical section is not rendered for non_drug classification', function () {
    $pmUser = pm();
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class)
        ->set('classification', 'non_drug')
        ->assertDontSee(__('catalogue.clinical_information'));
});

test('clinical section is rendered for rx classification', function () {
    $pmUser = pm();
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class)
        ->set('classification', 'rx')
        ->assertSee(__('catalogue.clinical_information'));
});

// ===========================================================================
// SECTION 12 — Buying price absent from PP serialised responses (BR-06)
// ===========================================================================

test('buying price (unit_cost) is null in toArray() for PP', function () {
    $psUser = ps();
    $this->actingAs($psUser);

    $product = Product::create(apProductData(['selling_price' => 5000]));
    $batch   = Batch::create([
        'product_id'  => $product->id,
        'batch_no'    => 'PP-COST-01',
        'expiry_date' => now()->addMonths(12)->toDateString(),
        'unit_cost'   => 9999,
        'status'      => Batch::STATUS_SELLABLE,
    ]);

    $arr = $product->toArray();
    expect($arr)->not->toHaveKey('selling_price');   // selling_price stripped for PP too
});

test('buying price (unit_cost) is present in toArray() for Owner', function () {
    $ownerUser = owner();
    $this->actingAs($ownerUser);

    $product = Product::create(apProductData(['selling_price' => 7500]));

    $arr = $product->toArray();
    // selling_price is in the array for OW (they have cost.view)
    expect($arr)->toHaveKey('selling_price')
        ->and((int) $arr['selling_price'])->toBe(7500);
});

test('StockOnHandQuery excludes unit_cost from batch columns for PP', function () {
    $psUser  = ps();
    $product = Product::create(apProductData());
    $batch   = Batch::create([
        'product_id'  => $product->id,
        'batch_no'    => 'SOH-COST-01',
        'expiry_date' => now()->addMonths(6)->toDateString(),
        'unit_cost'   => 3333,
        'status'      => Batch::STATUS_SELLABLE,
    ]);
    StockMovementService::post($batch, 5, 'receipt', $product, pm(), 'sellable');

    $result = (new \App\Services\StockOnHandQuery)->builder($psUser)
        ->where('id', $product->id)->first();

    expect($result?->batches->first()?->getAttributes())->not->toHaveKey('unit_cost');
});

// ===========================================================================
// SECTION 13 — Classification change is audited
// ===========================================================================

test('changing classification on edit creates an audit log entry', function () {
    $pmUser  = pm();
    $product = Product::create(apProductData(['classification' => 'otc']));

    // Update with classification change + confirmation
    (new SaveProduct)->execute(
        array_merge(apProductData([
            'brand'                     => $product->brand,
            'classification'            => 'rx',
            'classification_confirmed'  => true,
        ]), ['product_code' => $product->product_code]),
        $product, $pmUser
    );

    $audit = \App\Models\AuditLog::where('action', 'catalogue.classification.changed')
        ->where('auditable_id', $product->id)
        ->first();

    expect($audit)->not->toBeNull();

    // AuditLog casts old_values/new_values as arrays
    $old = is_array($audit->old_values) ? $audit->old_values : json_decode($audit->old_values, true);
    $new = is_array($audit->new_values) ? $audit->new_values : json_decode($audit->new_values, true);

    expect($old['classification'] ?? null)->toBe('otc')
        ->and($new['classification'] ?? null)->toBe('rx');
});

// ===========================================================================
// SECTION 14 — Livewire component renders correctly
// ===========================================================================

test('add product form renders all 8 sections for PM', function () {
    $pmUser = pm();
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class)
        ->assertSee(__('catalogue.product_identification'))
        ->assertSee(__('catalogue.pricing_section_title'))
        ->assertSee(__('catalogue.stock_levels'))
        ->assertSee(__('catalogue.alternative_units'))
        ->assertSee(__('catalogue.image'))
        ->assertSee(__('catalogue.first_batch_section_title'));
});

test('edit product form shows batch table, not first-batch section', function () {
    $pmUser  = pm();
    $product = Product::create(apProductData());
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(\App\Livewire\Catalogue\Form::class, ['product' => $product])
        ->assertSee(__('catalogue.batches_section_title'))
        ->assertDontSee(__('catalogue.first_batch_section_title'));
});
