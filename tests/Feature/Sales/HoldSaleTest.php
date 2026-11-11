<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Sales\CompleteSale;
use App\Actions\Sales\DiscardHeldSale;
use App\Actions\Sales\HoldSale;
use App\Actions\Sales\ResumeSale;
use App\Actions\Shifts\OpenShift;
use App\Livewire\Pos\Sale as PosSaleComponent;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\HeldSale;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Till;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-14: Hold and resume a sale.
 * BRD: FR-3.6. AC-23.
 */
beforeEach(fn () => seedRoles());

function makeHoldProduct(array $attrs = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'dosage_form' => 'capsule',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'capsule',
        'is_active' => true,
        'loose_allowed' => true,
    ], $attrs));
}

function makeBatchStock(Product $product, string $batchNo, int $quantity, Carbon $expiry): Batch
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

function setHoldPrice(Product $product, int $priceSantim): void
{
    app(SetSellingPrice::class)->execute(
        product: $product,
        price: $priceSantim,
        effectiveFrom: now()->subMinute(),
        reason: 'Initial retail price setup',
        unit: null,
        user: pm()
    );
}

function openShiftForHoldUser($user): Shift
{
    $till = Till::firstOrCreate(['name' => 'Hold Till 1'], ['is_active' => true]);

    return app(OpenShift::class)->execute($user, $till, 10000);
}

it('holds a sale storing the cart without reserving stock', function () {
    $staff = ps();
    $shift = openShiftForHoldUser($staff);

    $product = makeHoldProduct(['brand' => 'Paracetamol 500mg']);
    setHoldPrice($product, 1500);
    $batch = makeBatchStock($product, 'B-HOLD1', 50, now()->addYear());

    $cart = [
        [
            'temp_id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'brand' => $product->brand,
            'generic_name' => $product->generic_name,
            'classification' => 'otc',
            'unit_id' => null,
            'unit_name' => 'capsule',
            'quantity' => 10,
            'unit_price' => 1500,
            'subtotal' => 15000,
            'batch_id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'expiry_date' => $batch->expiry_date->format('Y-m-d'),
            'is_fefo_override' => false,
            'override_reason' => null,
        ],
    ];

    $heldSale = app(HoldSale::class)->execute(
        user: $staff,
        shift: $shift,
        cart: $cart,
        reference: 'Customer Hold #1'
    );

    expect($heldSale)->toBeInstanceOf(HeldSale::class)
        ->and($heldSale->status)->toBe(HeldSale::STATUS_HELD)
        ->and($heldSale->reference)->toBe('Customer Hold #1')
        ->and($heldSale->total)->toBe(15000)
        ->and($heldSale->user_id)->toBe($staff->id)
        ->and($heldSale->shift_id)->toBe($shift->id);

    // Stock must NOT be reserved or reduced
    expect($batch->batchStocks()->where('status', 'sellable')->first()->quantity)->toBe(50);

    // Audit log
    $audit = AuditLog::where('action', 'sale.held')
        ->where('auditable_type', HeldSale::class)
        ->where('auditable_id', $heldSale->id)
        ->first();
    expect($audit)->not->toBeNull()
        ->and($audit->new_values['items_count'])->toBe(1);
});

it('allows another sale to take the stock while a sale is held (E1)', function () {
    $staff1 = ps();
    $shift1 = openShiftForHoldUser($staff1);

    $product = makeHoldProduct(['brand' => 'Ibuprofen 400mg']);
    setHoldPrice($product, 2000);
    $batch = makeBatchStock($product, 'B-SHARED', 10, now()->addYear());

    // Staff 1 holds cart with 10 units
    $cart = [
        [
            'temp_id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'brand' => $product->brand,
            'generic_name' => $product->generic_name,
            'classification' => 'otc',
            'unit_id' => null,
            'unit_name' => 'tablet',
            'quantity' => 10,
            'unit_price' => 2000,
            'subtotal' => 20000,
            'batch_id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'expiry_date' => $batch->expiry_date->format('Y-m-d'),
            'is_fefo_override' => false,
            'override_reason' => null,
        ],
    ];

    $heldSale = app(HoldSale::class)->execute($staff1, $shift1, $cart, 'Hold for Phone Call');

    // Another cashier on till 2 completes a sale for all 10 units first
    $staff2 = ps();
    $till2 = Till::create(['name' => 'Till 2', 'is_active' => true]);
    app(OpenShift::class)->execute($staff2, $till2, 10000);

    $sale = app(CompleteSale::class)->execute(
        user: $staff2,
        items: [['product_id' => $product->id, 'quantity' => 10]],
        payments: [['method' => 'cash', 'amount' => 20000]],
        idempotencyKey: (string) Str::uuid()
    );

    expect($sale)->toBeInstanceOf(Sale::class);
    // Stock is now 0
    expect($batch->batchStocks()->where('status', 'sellable')->first()->quantity)->toBe(0);

    // Now Staff 1 resumes their held sale: line must be flagged as sold out (A1)
    $resumed = app(ResumeSale::class)->execute($staff1, $heldSale, $shift1);

    expect($resumed['held_sale']->status)->toBe(HeldSale::STATUS_RESUMED)
        ->and($resumed['has_issues'])->toBeTrue()
        ->and($resumed['cart'][0]['sold_out'])->toBeTrue()
        ->and($resumed['warnings'])->not->toBeEmpty();
});

it('re-validates lines on resume and flags changed prices and short stock (A1)', function () {
    $staff = ps();
    $shift = openShiftForHoldUser($staff);

    $product = makeHoldProduct(['brand' => 'Cough Syrup']);
    setHoldPrice($product, 5000); // Old price: 50.00 ETB
    $batch = makeBatchStock($product, 'B-PRICE', 5, now()->addYear());

    $cart = [
        [
            'temp_id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'brand' => $product->brand,
            'generic_name' => $product->generic_name,
            'classification' => 'otc',
            'unit_id' => null,
            'unit_name' => 'bottle',
            'quantity' => 10, // Wants 10, stock has only 5
            'unit_price' => 5000,
            'subtotal' => 50000,
            'batch_id' => $batch->id,
            'batch_no' => $batch->batch_no,
            'expiry_date' => $batch->expiry_date->format('Y-m-d'),
            'is_fefo_override' => false,
            'override_reason' => null,
        ],
    ];

    $heldSale = app(HoldSale::class)->execute($staff, $shift, $cart);

    // 1. Manager changes selling price to 65.00 ETB
    setHoldPrice($product, 6500);

    // 2. Resume held sale
    $result = app(ResumeSale::class)->execute($staff, $heldSale, $shift);

    expect($result['has_issues'])->toBeTrue();

    $resumedLine = $result['cart'][0];

    // Price change flagged
    expect($resumedLine['price_changed'])->toBeTrue()
        ->and($resumedLine['old_price'])->toBe(5000)
        ->and($resumedLine['unit_price'])->toBe(6500)
        ->and($resumedLine['subtotal'])->toBe(65000);

    // Stock shortage flagged (wants 10, only 5 available)
    expect($resumedLine['insufficient_stock'])->toBeTrue()
        ->and($resumedLine['available_stock'])->toBe(5);

    // Audit log
    $audit = AuditLog::where('action', 'sale.resumed')->latest('id')->first();
    expect($audit)->not->toBeNull();
});

it('ensures staff only see and resume their own held sales (AC-23)', function () {
    $staff1 = ps();
    $staff2 = ps();
    $manager = pm();

    $shift1 = openShiftForHoldUser($staff1);

    $product = makeHoldProduct();
    setHoldPrice($product, 1000);
    makeBatchStock($product, 'B-STAFF', 20, now()->addYear());

    $cart = [
        [
            'temp_id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'brand' => $product->brand,
            'generic_name' => $product->generic_name,
            'classification' => 'otc',
            'unit_id' => null,
            'unit_name' => 'unit',
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'batch_id' => 1,
            'batch_no' => 'B-STAFF',
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'is_fefo_override' => false,
            'override_reason' => null,
        ],
    ];

    $heldSale1 = app(HoldSale::class)->execute($staff1, $shift1, $cart, 'Staff 1 Hold');

    // Scope check: Staff 2 query should NOT see Staff 1 held sales
    expect(HeldSale::held()->forUser($staff2)->count())->toBe(0)
        ->and(HeldSale::held()->forUser($staff1)->count())->toBe(1);

    // Manager can see all held sales
    expect(HeldSale::held()->forUser($manager)->count())->toBe(1);

    // Staff 2 attempting to resume Staff 1 held sale -> throws AuthorizationException
    $till2 = Till::create(['name' => 'Till 2B', 'is_active' => true]);
    $shift2 = app(OpenShift::class)->execute($staff2, $till2, 10000);

    expect(fn () => app(ResumeSale::class)->execute($staff2, $heldSale1, $shift2))
        ->toThrow(AuthorizationException::class);
});

it('discards a held sale with audit trail', function () {
    $staff = ps();
    $shift = openShiftForHoldUser($staff);

    $product = makeHoldProduct();
    setHoldPrice($product, 1000);
    makeBatchStock($product, 'B-DISC', 20, now()->addYear());

    $cart = [
        [
            'temp_id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'brand' => $product->brand,
            'generic_name' => $product->generic_name,
            'classification' => 'otc',
            'unit_id' => null,
            'unit_name' => 'unit',
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'batch_id' => 1,
            'batch_no' => 'B-DISC',
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'is_fefo_override' => false,
            'override_reason' => null,
        ],
    ];

    $held = app(HoldSale::class)->execute($staff, $shift, $cart);

    app(DiscardHeldSale::class)->execute($staff, $held, 'Customer cancelled transaction');

    expect($held->refresh()->status)->toBe(HeldSale::STATUS_DISCARDED)
        ->and($held->discard_reason)->toBe('Customer cancelled transaction')
        ->and($held->discarded_at)->not->toBeNull();

    $audit = AuditLog::where('action', 'sale.held.discarded')->latest('id')->first();
    expect($audit)->not->toBeNull();
});

it('holds and resumes cart via Livewire Pos\\Sale component', function () {
    $staff = ps();
    openShiftForHoldUser($staff);

    $product = makeHoldProduct(['brand' => 'Zinc 20mg']);
    setHoldPrice($product, 1200);
    makeBatchStock($product, 'B-ZINC', 50, now()->addYear());

    actAs($staff);

    $test = Livewire::test(PosSaleComponent::class)
        ->call('addProduct', $product->id)
        ->assertSee('Zinc 20mg')
        ->call('openHoldModal')
        ->set('holdReferenceInput', 'Customer Walkout')
        ->call('confirmHold')
        ->assertHasNoErrors();

    // Cart is now empty on the screen
    expect($test->get('cart'))->toBeEmpty();

    $heldSale = HeldSale::where('reference', 'Customer Walkout')->first();
    expect($heldSale)->not->toBeNull()
        ->and($heldSale->isHeld())->toBeTrue();

    // Resume the held sale via Livewire
    $test->call('openHeldSalesModal')
        ->assertSee('Customer Walkout')
        ->call('resumeSale', $heldSale->id)
        ->assertHasNoErrors();

    // Cart is restored
    expect($test->get('cart'))->not->toBeEmpty()
        ->and($test->get('cart')[0]['brand'])->toBe('Zinc 20mg');
});
