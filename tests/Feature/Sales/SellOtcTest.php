<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Sales\CompleteSale;
use App\Actions\Shifts\OpenShift;
use App\Exceptions\FefoOverrideException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PaymentMismatchException;
use App\Exceptions\SellabilityException;
use App\Livewire\Pos\Sale as PosSaleComponent;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\QuarantineRecord;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\Till;
use App\Services\FefoBatchResolver;
use App\Services\SellabilityGuard;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-13: Sell OTC and non-drug items
 * BRD: FR-3.1..3.5, 3.11, 2.4; BR-01..BR-05, BR-20.
 * AC-01, AC-02, AC-04, AC-20, AC-22, AC-31, AC-36.
 */
beforeEach(fn () => seedRoles());

function createTestOtcProduct(array $attrs = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Paracetamol 500mg',
        'generic_name' => 'Paracetamol',
        'dosage_form' => 'tablet',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'tablet',
        'is_active' => true,
        'loose_allowed' => true,
    ], $attrs));
}

function setupBatchWithStock(Product $product, string $batchNo, int $quantity, Carbon $expiry, string $status = 'sellable'): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => $batchNo,
        'expiry_date' => $expiry->format('Y-m-d'),
        'unit_cost' => 1500, // 15.00 ETB
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $quantity,
        'status' => $status,
    ]);

    return $batch;
}

function setTestPrice(Product $product, int $priceSantim, ?ProductUnit $unit = null): ProductPrice
{
    $manager = pm();

    return app(SetSellingPrice::class)->execute(
        product: $product,
        price: $priceSantim,
        effectiveFrom: now()->subMinute(),
        reason: 'Initial retail price setup',
        unit: $unit,
        user: $manager
    );
}

function openShiftForUser($user): Shift
{
    $till = Till::firstOrCreate(['name' => 'Till 1'], ['is_active' => true]);

    return app(OpenShift::class)->execute($user, $till, 10000);
}

it('blocks expired, recalled, quarantined, and inactive products from sale with audit log (AC-01)', function () {
    $staff = ps();
    openShiftForUser($staff);

    // 1. Inactive product
    $inactiveProduct = createTestOtcProduct(['brand' => 'Inactive Med', 'is_active' => false]);
    $batch1 = setupBatchWithStock($inactiveProduct, 'B-INACT', 50, now()->addYear());
    expect(fn () => SellabilityGuard::check($inactiveProduct, $batch1, 1, $staff, 'sell'))
        ->toThrow(SellabilityException::class);

    $auditInactive = AuditLog::where('action', 'sale.blocked')
        ->where('metadata->reason', 'inactive_product')
        ->latest('id')
        ->first();
    expect($auditInactive)->not->toBeNull();

    // 2. Expired batch
    $activeProduct = createTestOtcProduct(['brand' => 'Active Med']);
    $expiredBatch = setupBatchWithStock($activeProduct, 'B-EXPIRED', 50, now()->subDay());
    expect(fn () => SellabilityGuard::check($activeProduct, $expiredBatch, 1, $staff, 'sell'))
        ->toThrow(SellabilityException::class);

    $auditExpired = AuditLog::where('action', 'sale.blocked')
        ->where('metadata->reason', 'expired_batch')
        ->latest('id')
        ->first();
    expect($auditExpired)->not->toBeNull();

    // 3. Recalled batch
    $recalledBatch = setupBatchWithStock($activeProduct, 'B-RECALL', 50, now()->addMonths(6));
    QuarantineRecord::create([
        'batch_id' => $recalledBatch->id,
        'quantity' => 50,
        'reason' => QuarantineRecord::REASON_RECALLED,
        'status' => QuarantineRecord::STATUS_OPEN,
        'created_by' => $staff->id,
        'note' => 'Manufacturer recall notice',
    ]);
    expect(fn () => SellabilityGuard::check($activeProduct, $recalledBatch, 1, $staff, 'sell'))
        ->toThrow(SellabilityException::class);

    $auditRecall = AuditLog::where('action', 'sale.blocked')
        ->where('metadata->reason', 'recalled_batch')
        ->latest('id')
        ->first();
    expect($auditRecall)->not->toBeNull();

    // 4. Quarantined stock (no sellable quantity)
    $quarantinedBatch = setupBatchWithStock($activeProduct, 'B-QUAR', 50, now()->addMonths(6), 'quarantine');
    expect(fn () => SellabilityGuard::check($activeProduct, $quarantinedBatch, 1, $staff, 'sell'))
        ->toThrow(SellabilityException::class);

    $auditQuar = AuditLog::where('action', 'sale.blocked')
        ->where('metadata->reason', 'quarantined_stock')
        ->latest('id')
        ->first();
    expect($auditQuar)->not->toBeNull();
});

it('resolves batches via FEFO picking earliest-expiry first and splits across batches (AC-02)', function () {
    $staff = ps();
    openShiftForUser($staff);

    $product = createTestOtcProduct(['brand' => 'Amoxicillin 250mg']);
    setTestPrice($product, 2000); // 20.00 ETB

    // Batch 1 expires in 3 months (qty 10)
    $batchEarlier = setupBatchWithStock($product, 'B-EARLY', 10, now()->addMonths(3));
    // Batch 2 expires in 12 months (qty 50)
    $batchLater = setupBatchWithStock($product, 'B-LATER', 50, now()->addMonths(12));

    // Request 5 units -> should all come from B-EARLY
    $allocations = FefoBatchResolver::resolve($product, 5, actor: $staff);
    expect($allocations)->toHaveCount(1)
        ->and($allocations[0]['batch']->id)->toBe($batchEarlier->id)
        ->and($allocations[0]['quantity'])->toBe(5);

    // Request 15 units -> splits: 10 from B-EARLY, 5 from B-LATER
    $splitAllocations = FefoBatchResolver::resolve($product, 15, actor: $staff);
    expect($splitAllocations)->toHaveCount(2)
        ->and($splitAllocations[0]['batch']->id)->toBe($batchEarlier->id)
        ->and($splitAllocations[0]['quantity'])->toBe(10)
        ->and($splitAllocations[1]['batch']->id)->toBe($batchLater->id)
        ->and($splitAllocations[1]['quantity'])->toBe(5);
});

it('requires a reason for FEFO batch override and logs sale.fefo_override audit entry (AC-02, A2)', function () {
    $staff = ps();
    openShiftForUser($staff);

    $product = createTestOtcProduct(['brand' => 'Ibuprofen 400mg']);
    setTestPrice($product, 1500);

    $batchEarlier = setupBatchWithStock($product, 'B-FIRST', 20, now()->addMonths(2));
    $batchLater = setupBatchWithStock($product, 'B-SECOND', 20, now()->addMonths(8));

    // Override without a reason -> throws FefoOverrideException
    expect(fn () => FefoBatchResolver::resolve(
        product: $product,
        requestedQuantity: 5,
        overrideBatchId: $batchLater->id,
        overrideReason: '',
        actor: $staff
    ))->toThrow(FefoOverrideException::class);

    // Override with reason -> succeeds and audits
    $override = FefoBatchResolver::resolve(
        product: $product,
        requestedQuantity: 5,
        overrideBatchId: $batchLater->id,
        overrideReason: 'Customer requested blister pack from new shipment',
        actor: $staff
    );

    expect($override)->toHaveCount(1)
        ->and($override[0]['batch']->id)->toBe($batchLater->id)
        ->and($override[0]['is_override'])->toBeTrue()
        ->and($override[0]['override_reason'])->toBe('Customer requested blister pack from new shipment');

    $log = AuditLog::where('action', 'sale.fefo_override')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['product_id'])->toBe($product->id)
        ->and($log->metadata['reason'])->toBe('Customer requested blister pack from new shipment')
        ->and($log->new_values['selected_batch_id'])->toBe($batchLater->id);
});

it('completes a 5-item cash sale in under 60 seconds of scripted steps and reduces stock (AC-04)', function () {
    $staff = ps();
    $shift = openShiftForUser($staff);

    $startTime = microtime(true);

    // Create 5 OTC products with prices and batches
    $items = [];
    $totalExpectedSantim = 0;

    for ($i = 1; $i <= 5; $i++) {
        $p = createTestOtcProduct(['brand' => "OTC Product {$i}"]);
        setTestPrice($p, $i * 1000); // 10, 20, 30, 40, 50 ETB
        setupBatchWithStock($p, "BATCH-{$i}", 100, now()->addYear());

        $qty = $i;
        $items[] = [
            'product_id' => $p->id,
            'quantity' => $qty,
        ];
        $totalExpectedSantim += $qty * ($i * 1000);
    }

    $action = app(CompleteSale::class);
    $sale = $action->execute(
        user: $staff,
        items: $items,
        payments: [
            ['method' => 'cash', 'amount' => $totalExpectedSantim],
        ],
        idempotencyKey: (string) Str::uuid()
    );

    $duration = microtime(true) - $startTime;

    expect($duration)->toBeLessThan(60.0) // Well under 60 seconds
        ->and($sale)->toBeInstanceOf(Sale::class)
        ->and($sale->total)->toBe($totalExpectedSantim)
        ->and($sale->receipt_no)->toMatch('/^REC-\d{4}-\d{5}$/')
        ->and($sale->lines)->toHaveCount(5)
        ->and($sale->payments)->toHaveCount(1);

    // Verify stock reduction in batch_stocks
    foreach ($items as $idx => $item) {
        $stock = BatchStock::whereHas('batch', fn ($q) => $q->where('product_id', $item['product_id']))
            ->where('status', 'sellable')
            ->first();
        expect($stock->quantity)->toBe(100 - $item['quantity']);
    }

    // Verify audit log
    $audit = AuditLog::where('action', 'sale.completed')
        ->where('auditable_type', Sale::class)
        ->where('auditable_id', $sale->id)
        ->first();
    expect($audit)->not->toBeNull();
});

it('handles split payments and refuses payment mismatch (AC-22, E3)', function () {
    $staff = ps();
    openShiftForUser($staff);

    $product = createTestOtcProduct();
    setTestPrice($product, 5000); // 50.00 ETB
    setupBatchWithStock($product, 'B-SPLIT', 10, now()->addMonths(6));

    $action = app(CompleteSale::class);

    // 1. Payment Mismatch (Total 5000, Paid 4000) -> Throws PaymentMismatchException
    expect(fn () => $action->execute(
        user: $staff,
        items: [['product_id' => $product->id, 'quantity' => 1]],
        payments: [['method' => 'cash', 'amount' => 4000]],
        idempotencyKey: (string) Str::uuid()
    ))->toThrow(PaymentMismatchException::class);

    // 2. Valid Split Payment (Total 5000: 3000 Cash + 2000 Telebirr) -> Succeeds
    $sale = $action->execute(
        user: $staff,
        items: [['product_id' => $product->id, 'quantity' => 1]],
        payments: [
            ['method' => 'cash', 'amount' => 3000],
            ['method' => 'mobile_money', 'amount' => 2000, 'reference' => 'TEL-998877'],
        ],
        idempotencyKey: (string) Str::uuid()
    );

    expect($sale->payments)->toHaveCount(2)
        ->and($sale->payments->where('method', 'cash')->first()->amount)->toBe(3000)
        ->and($sale->payments->where('method', 'mobile_money')->first()->amount)->toBe(2000)
        ->and($sale->payments->where('method', 'mobile_money')->first()->reference)->toBe('TEL-998877');
});

it('is idempotent: double submission with identical idempotency key creates only one sale (AC-20, E5)', function () {
    $staff = ps();
    openShiftForUser($staff);

    $product = createTestOtcProduct();
    setTestPrice($product, 2500);
    $batch = setupBatchWithStock($product, 'B-IDEM', 50, now()->addYear());

    $action = app(CompleteSale::class);
    $key = (string) Str::uuid();

    $sale1 = $action->execute(
        user: $staff,
        items: [['product_id' => $product->id, 'quantity' => 2]],
        payments: [['method' => 'cash', 'amount' => 5000]],
        idempotencyKey: $key
    );

    // Second click with same idempotency key
    $sale2 = $action->execute(
        user: $staff,
        items: [['product_id' => $product->id, 'quantity' => 2]],
        payments: [['method' => 'cash', 'amount' => 5000]],
        idempotencyKey: $key
    );

    expect($sale2->id)->toBe($sale1->id)
        ->and(Sale::where('idempotency_key', $key)->count())->toBe(1);

    // Stock deducted only ONCE (50 - 2 = 48)
    expect($batch->batchStocks()->where('status', 'sellable')->first()->quantity)->toBe(48);
});

it('marks reprints with COPY on the receipt view and audits the reprint (E4)', function () {
    $staff = ps();
    openShiftForUser($staff);

    $product = createTestOtcProduct();
    setTestPrice($product, 3000);
    setupBatchWithStock($product, 'B-PRINT', 20, now()->addYear());

    $sale = app(CompleteSale::class)->execute(
        user: $staff,
        items: [['product_id' => $product->id, 'quantity' => 1]],
        payments: [['method' => 'cash', 'amount' => 3000]],
        idempotencyKey: (string) Str::uuid()
    );

    actAs($staff);

    // 1. Initial view is NOT marked copy
    $response = $this->get(route('sales.receipt', $sale));
    $response->assertOk()
        ->assertSee($sale->receipt_no)
        ->assertDontSee('*** COPY / ቅጂ ***');

    // 2. Reprint view (e.g. reprint parameter) is marked COPY and audited
    $reprintResponse = $this->get(route('sales.receipt', ['sale' => $sale, 'reprint' => 1]));
    $reprintResponse->assertOk()
        ->assertSee('*** COPY / ቅጂ ***');

    expect($sale->refresh()->reprint_count)->toBe(1);

    $reprintLog = AuditLog::where('action', 'sale.reprinted')
        ->where('auditable_type', Sale::class)
        ->where('auditable_id', $sale->id)
        ->first();
    expect($reprintLog)->not->toBeNull();
});

it('renders thermal receipt in both English and Amharic correctly (AC-31)', function () {
    $staff = ps();
    openShiftForUser($staff);

    $product = createTestOtcProduct(['brand' => 'Paracetamol 500mg']);
    setTestPrice($product, 1500);
    setupBatchWithStock($product, 'B-ETH', 20, now()->addYear());

    $sale = app(CompleteSale::class)->execute(
        user: $staff,
        items: [['product_id' => $product->id, 'quantity' => 1]],
        payments: [['method' => 'cash', 'amount' => 1500]],
        idempotencyKey: (string) Str::uuid()
    );

    actAs($staff);

    $response = $this->get(route('sales.receipt', $sale));
    $response->assertOk()
        ->assertSee('OPERATIONAL RECEIPT')
        ->assertSee('የክወና ደረሰኝ')
        ->assertSee('NOT A TAX INVOICE')
        ->assertSee('የታክስ ደረሰኝ አይደለም')
        ->assertSee('TOTAL')
        ->assertSee('ድምር');
});

it('hides cost from pharmacy staff (BR-06, rule 10)', function () {
    $staff = ps();
    $manager = pm();

    expect($staff->hasPermission('cost.view'))->toBeFalse()
        ->and($manager->hasPermission('cost.view'))->toBeTrue();

    $product = createTestOtcProduct();
    $batch = setupBatchWithStock($product, 'B-COST', 10, now()->addYear());

    // Staff cannot view cost
    actAs($staff);
    $response = $this->get(route('catalogue.show', $product));
    $response->assertOk();
    if ($batch->unit_cost) {
        $response->assertDontSee(number_format($batch->unit_cost / 100, 2));
    }
});

it('refuses sale if quantity requested exceeds available sellable stock (E2)', function () {
    $staff = ps();
    openShiftForUser($staff);

    $product = createTestOtcProduct();
    setTestPrice($product, 1000);
    setupBatchWithStock($product, 'B-LOW', 3, now()->addYear());

    expect(fn () => app(CompleteSale::class)->execute(
        user: $staff,
        items: [['product_id' => $product->id, 'quantity' => 10]], // wants 10, has only 3
        payments: [['method' => 'cash', 'amount' => 10000]],
        idempotencyKey: (string) Str::uuid()
    ))->toThrow(InsufficientStockException::class);
});

it('refuses selling prescription and controlled items at the OTC till (A3)', function () {
    $staff = ps();
    openShiftForUser($staff);

    $rxProduct = createTestOtcProduct(['brand' => 'Amoxicillin 500mg', 'classification' => 'rx']);
    setTestPrice($rxProduct, 2500);
    setupBatchWithStock($rxProduct, 'B-RX', 50, now()->addYear());

    expect(fn () => app(CompleteSale::class)->execute(
        user: $staff,
        items: [['product_id' => $rxProduct->id, 'quantity' => 1]],
        payments: [['method' => 'cash', 'amount' => 2500]],
        idempotencyKey: (string) Str::uuid()
    ))->toThrow(InvalidArgumentException::class);
});

it('runs Pos\\Sale Livewire component to search, add item, and complete sale', function () {
    $staff = ps();
    openShiftForUser($staff);

    $product = createTestOtcProduct(['brand' => 'Vitamin C 1000mg', 'barcode' => '789123456']);
    setTestPrice($product, 3500);
    setupBatchWithStock($product, 'B-VITC', 100, now()->addYear());

    actAs($staff);

    Livewire::test(PosSaleComponent::class)
        ->set('search', 'Vitamin')
        ->assertSee('Vitamin C 1000mg')
        ->call('addProduct', $product->id)
        ->assertSee('35.00')
        ->call('setExactCash')
        ->call('submitSale')
        ->assertHasNoErrors()
        ->assertRedirect();

    $completedSale = Sale::where('total', 3500)->first();
    expect($completedSale)->not->toBeNull()
        ->and($completedSale->user_id)->toBe($staff->id);
});
