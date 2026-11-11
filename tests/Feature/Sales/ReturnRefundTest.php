<?php

use App\Actions\Sales\ProcessReturn;
use App\Actions\Shifts\OpenShift;
use App\Approvals\Handlers\RefundApprovalHandler;
use App\Livewire\Returns\Process as ReturnsProcess;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\QuarantineRecord;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnLine;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Till;
use App\Models\User;
use App\Services\SettingsRepository;
use App\Services\ShiftExpectedCalculator;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-16: Process customer return and refund.
 * BRD: FR-3.8; BR-18. AC-15.
 */
beforeEach(fn () => seedRoles());

function makeReturnProduct(array $attrs = []): Product
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

function makeReturnBatch(Product $product, int $qty = 100): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'RET-'.Str::random(5),
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 500,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $qty,
        'status' => 'sellable',
    ]);

    return $batch;
}

function makeReturnShift(User $user): Shift
{
    $till = Till::create(['name' => 'Till-R-'.Str::random(4), 'is_active' => true]);

    return app(OpenShift::class)->execute($user, $till, 10000);
}

function setSetting(string $key, mixed $val): void
{
    app(SettingsRepository::class)->put($key, $val);
}

function createSaleForReturn(User $staff, Shift $shift, Batch $batch, int $qty = 5, int $unitPrice = 2000, ?Carbon $completedAt = null): Sale
{
    $sale = Sale::create([
        'receipt_no' => 'RCS-'.Str::random(8),
        'shift_id' => $shift->id,
        'user_id' => $staff->id,
        'status' => 'completed',
        'total' => $qty * $unitPrice,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => $completedAt ?? now(),
    ]);

    $sale->lines()->create([
        'product_id' => $batch->product_id,
        'batch_id' => $batch->id,
        'quantity' => $qty,
        'unit_price' => $unitPrice,
        'subtotal' => $qty * $unitPrice,
        'is_fefo_override' => false,
    ]);

    $sale->payments()->create([
        'method' => 'cash',
        'amount' => $qty * $unitPrice,
    ]);

    return $sale;
}

it('resaleable item returns to sellable stock and non-resaleable goes to quarantine (AC-15, BR-18)', function () {
    $staff = ps();
    $shift = makeReturnShift($staff);
    $product = makeReturnProduct();
    $batch = makeReturnBatch($product, qty: 50);

    // Initial sale of 4 items
    $sale = createSaleForReturn($staff, $shift, $batch, qty: 4, unitPrice: 1000);
    $line = $sale->lines->first();

    // Set high refund limit so it completes immediately
    setSetting('sales.refund_limit', 100000); // 1,000 ETB

    // Return 2 items: 1 resaleable, 1 damaged (not resaleable)
    $stockBefore = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->value('quantity');

    $result = app(ProcessReturn::class)->execute(
        user: $staff,
        receiptNo: $sale->receipt_no,
        items: [
            [
                'sale_line_id' => $line->id,
                'quantity' => 1,
                'assessment' => SaleReturnLine::ASSESSMENT_RESALEABLE,
                'reason' => 'Customer bought too many',
            ],
            [
                'sale_line_id' => $line->id,
                'quantity' => 1,
                'assessment' => SaleReturnLine::ASSESSMENT_NOT_RESALEABLE,
                'reason' => 'Package seal damaged by customer',
            ],
        ],
        shift: $shift
    );

    expect($result['requires_approval'])->toBeFalse()
        ->and($result['sale_return']->isCompleted())->toBeTrue()
        ->and($result['sale_return']->total_refund)->toBe(2000); // 20.00 ETB

    // 1. Resaleable item returned to sellable stock
    $stockAfter = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->value('quantity');
    expect($stockAfter)->toBe($stockBefore + 1);

    // 2. Damaged item routed to quarantine
    $quarantineStock = BatchStock::where('batch_id', $batch->id)->where('status', 'quarantine')->value('quantity');
    expect($quarantineStock)->toBe(1);

    // 3. Quarantine record created
    $quarantineRecord = QuarantineRecord::where('batch_id', $batch->id)->first();
    expect($quarantineRecord)->not->toBeNull()
        ->and($quarantineRecord->quantity)->toBe(1)
        ->and($quarantineRecord->isOpen())->toBeTrue();

    // 4. Stock movements verify ledger
    expect(StockMovement::where('batch_id', $batch->id)->where('type', 'return')->exists())->toBeTrue()
        ->and(StockMovement::where('batch_id', $batch->id)->where('type', 'quarantine')->exists())->toBeTrue();
});

it('refund above limit waits for PM approval before money or stock moves', function () {
    $staff = ps();
    $manager = pm();
    $shift = makeReturnShift($staff);
    $product = makeReturnProduct();
    $batch = makeReturnBatch($product, qty: 50);

    $sale = createSaleForReturn($staff, $shift, $batch, qty: 5, unitPrice: 10000); // 500 ETB total
    $line = $sale->lines->first();

    // Set refund limit to 200 ETB (20,000 santim)
    setSetting('sales.refund_limit', 20000);

    $stockBefore = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->value('quantity');

    // Refund of 3 units = 300 ETB (> 200 ETB limit)
    $result = app(ProcessReturn::class)->execute(
        user: $staff,
        receiptNo: $sale->receipt_no,
        items: [
            [
                'sale_line_id' => $line->id,
                'quantity' => 3,
                'assessment' => SaleReturnLine::ASSESSMENT_RESALEABLE,
            ],
        ],
        shift: $shift
    );

    expect($result['requires_approval'])->toBeTrue()
        ->and($result['sale_return']->isPendingApproval())->toBeTrue()
        ->and($result['approval_request'])->not->toBeNull();

    // No stock moved yet!
    $stockDuringPending = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->value('quantity');
    expect($stockDuringPending)->toBe($stockBefore);

    // PM approves the request via the approval handler
    app(RefundApprovalHandler::class)->onApproved($result['approval_request'], $manager);

    $saleReturn = $result['sale_return']->fresh();
    expect($saleReturn->isCompleted())->toBeTrue();

    // Stock has now moved after approval!
    $stockAfterApproval = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->value('quantity');
    expect($stockAfterApproval)->toBe($stockBefore + 3);
});

it('rejection of refund leaves stock unchanged and marks return rejected', function () {
    $staff = ps();
    $manager = pm();
    $shift = makeReturnShift($staff);
    $product = makeReturnProduct();
    $batch = makeReturnBatch($product, qty: 50);

    $sale = createSaleForReturn($staff, $shift, $batch, qty: 2, unitPrice: 10000);
    $line = $sale->lines->first();

    setSetting('sales.refund_limit', 5000); // low limit to trigger approval

    $stockBefore = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->value('quantity');

    $result = app(ProcessReturn::class)->execute(
        user: $staff,
        receiptNo: $sale->receipt_no,
        items: [
            ['sale_line_id' => $line->id, 'quantity' => 1, 'assessment' => 'resaleable'],
        ],
        shift: $shift
    );

    app(RefundApprovalHandler::class)->onRejected($result['approval_request'], $manager);

    $saleReturn = $result['sale_return']->fresh();
    expect($saleReturn->status)->toBe(SaleReturn::STATUS_REJECTED);

    $stockAfter = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->value('quantity');
    expect($stockAfter)->toBe($stockBefore);
});

it('no receipt is refused (E1)', function () {
    $staff = ps();
    $shift = makeReturnShift($staff);

    expect(fn () => app(ProcessReturn::class)->execute(
        user: $staff,
        receiptNo: '',
        items: [['sale_line_id' => 1, 'quantity' => 1]],
        shift: $shift
    ))->toThrow(InvalidArgumentException::class);
});

it('quantity above sold quantity is refused (E2)', function () {
    $staff = ps();
    $shift = makeReturnShift($staff);
    $product = makeReturnProduct();
    $batch = makeReturnBatch($product);

    $sale = createSaleForReturn($staff, $shift, $batch, qty: 2);
    $line = $sale->lines->first();

    // Attempting to return 3 when only 2 were sold must throw
    expect(fn () => app(ProcessReturn::class)->execute(
        user: $staff,
        receiptNo: $sale->receipt_no,
        items: [
            ['sale_line_id' => $line->id, 'quantity' => 3, 'assessment' => 'resaleable'],
        ],
        shift: $shift
    ))->toThrow(RuntimeException::class);
});

it('sale outside return window is refused (A1)', function () {
    $staff = ps();
    $shift = makeReturnShift($staff);
    $product = makeReturnProduct();
    $batch = makeReturnBatch($product);

    setSetting('sales.return_window_days', 7);

    // Sale completed 10 days ago (outside 7-day window)
    $sale = createSaleForReturn($staff, $shift, $batch, qty: 1, completedAt: now()->subDays(10));
    $line = $sale->lines->first();

    expect(fn () => app(ProcessReturn::class)->execute(
        user: $staff,
        receiptNo: $sale->receipt_no,
        items: [
            ['sale_line_id' => $line->id, 'quantity' => 1, 'assessment' => 'resaleable'],
        ],
        shift: $shift
    ))->toThrow(RuntimeException::class);
});

it('completed refund reduces expected cash on shift', function () {
    $staff = ps();
    $shift = makeReturnShift($staff);
    $product = makeReturnProduct();
    $batch = makeReturnBatch($product);

    // Float is 10000 santim (100 ETB)
    // Sale adds 5000 santim cash (50 ETB)
    $sale = createSaleForReturn($staff, $shift, $batch, qty: 1, unitPrice: 5000);
    $line = $sale->lines->first();

    setSetting('sales.refund_limit', 100000);

    // Process return for the full 5000 santim
    app(ProcessReturn::class)->execute(
        user: $staff,
        receiptNo: $sale->receipt_no,
        items: [
            ['sale_line_id' => $line->id, 'quantity' => 1, 'assessment' => 'resaleable'],
        ],
        shift: $shift
    );

    // Expected cash: 10000 float + 5000 sale - 5000 refund = 10000 santim
    $expected = ShiftExpectedCalculator::calculate($shift);
    expect($expected['cash'])->toBe(10000);
});

it('processes return through livewire and redirects to receipt', function () {
    $staff = ps();
    $shift = makeReturnShift($staff);
    $product = makeReturnProduct();
    $batch = makeReturnBatch($product);

    $sale = createSaleForReturn($staff, $shift, $batch, qty: 3, unitPrice: 1500);
    $line = $sale->lines->first();

    setSetting('sales.refund_limit', 100000);

    Livewire::actingAs($staff)
        ->test(ReturnsProcess::class)
        ->set('receiptNo', $sale->receipt_no)
        ->call('searchSale')
        ->assertSee($sale->receipt_no)
        ->set("returnLines.{$line->id}.selected", true)
        ->set("returnLines.{$line->id}.quantity", 2)
        ->set("returnLines.{$line->id}.assessment", 'resaleable')
        ->call('submitReturn')
        ->assertRedirect();

    expect(SaleReturn::where('sale_id', $sale->id)->count())->toBe(1);
});
