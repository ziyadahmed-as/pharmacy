<?php

use App\Actions\Inventory\ConfirmStockReceipt;
use App\Actions\Inventory\SubmitStockReceipt;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockReceipt;
use Illuminate\Validation\ValidationException;

function makeTestProduct(array $attr = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Paracetamol 500mg',
        'generic_name' => 'Paracetamol',
        'classification' => 'otc',
        'is_active' => true,
    ], $attr));
}

test('receipt creates batches and is sellable only after PM confirms (AC-05)', function () {
    $product = makeTestProduct();
    $staff = ps();
    $pmUser = pm();

    $submit = new SubmitStockReceipt;
    $confirm = new ConfirmStockReceipt;

    $data = [
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'IDEM_KEY_01',
        'lines' => [
            [
                'product_id' => $product->id,
                'batch_no' => 'B1001',
                'manufacturing_date' => now()->subMonths(2)->format('Y-m-d'),
                'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
                'quantity' => 50,
                'flag' => 'ok',
            ],
        ],
    ];

    $receipt = $submit->execute($data, $staff);

    expect($receipt->status)->toBe('pending_confirmation');
    // Stock is NOT posted yet
    expect(BatchStock::where('status', 'sellable')->sum('quantity'))->toBe(0);

    // PM confirms
    $confirmed = $confirm->execute($receipt, $pmUser);

    expect($confirmed->status)->toBe('posted');

    $batch = Batch::where('product_id', $product->id)->where('batch_no', 'B1001')->first();
    expect($batch)->not->toBeNull();

    $stock = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->first();
    expect($stock->quantity)->toBe(50);

    // Ledger movement written
    $movement = StockMovement::where('batch_id', $batch->id)->first();
    expect($movement)->not->toBeNull()
        ->and($movement->quantity)->toBe(50)
        ->and($movement->balance_after)->toBe(50)
        ->and($movement->type)->toBe('receipt');
});

test('damaged items go to quarantine, short/over flagged (AC-06)', function () {
    $product = makeTestProduct();
    $pmUser = pm();

    $submit = new SubmitStockReceipt;
    $confirm = new ConfirmStockReceipt;

    $receipt = $submit->execute([
        'supplier_name' => 'Medtech',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'IDEM_KEY_02',
        'lines' => [
            [
                'product_id' => $product->id,
                'batch_no' => 'B_DAMAGED',
                'manufacturing_date' => now()->subMonths(1)->format('Y-m-d'),
                'expiry_date' => now()->addMonths(10)->format('Y-m-d'),
                'quantity' => 10,
                'flag' => 'damaged',
            ],
        ],
    ], $pmUser);

    $confirm->execute($receipt, $pmUser);

    $batch = Batch::where('batch_no', 'B_DAMAGED')->first();
    $quarantineStock = BatchStock::where('batch_id', $batch->id)->where('status', 'quarantine')->first();
    $sellableStock = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->first();

    expect($quarantineStock->quantity)->toBe(10)
        ->and($sellableStock)->toBeNull();
});

test('short dated line rejected unless PM overrides with reason (AC-06)', function () {
    $product = makeTestProduct();
    $submit = new SubmitStockReceipt;

    // Line with only 30 days remaining (default min is 90)
    $shortDatedLine = [
        'product_id' => $product->id,
        'batch_no' => 'B_SHORT_DATED',
        'expiry_date' => now()->addDays(30)->format('Y-m-d'),
        'quantity' => 20,
    ];

    $payload = [
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'IDEM_KEY_SHORT',
        'lines' => [$shortDatedLine],
    ];

    // Staff submission rejected
    expect(fn () => $submit->execute($payload, ps()))
        ->toThrow(ValidationException::class);

    // PM override without reason rejected
    expect(fn () => $submit->execute($payload, pm(), overrideShelfLife: true, overrideReason: ''))
        ->toThrow(ValidationException::class);

    // PM override with reason accepted
    $receipt = $submit->execute($payload, pm(), overrideShelfLife: true, overrideReason: 'Urgent stockout override');
    expect($receipt)->toBeInstanceOf(StockReceipt::class);
});

test('existing batch gets quantity added (A2)', function () {
    $product = makeTestProduct();
    $pmUser = pm();
    $submit = new SubmitStockReceipt;
    $confirm = new ConfirmStockReceipt;

    $line = [
        'product_id' => $product->id,
        'batch_no' => 'REUSED_BATCH',
        'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
        'quantity' => 20,
    ];

    // First receipt: +20
    $r1 = $submit->execute([
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'IDEM_A1',
        'lines' => [$line],
    ], $pmUser);
    $confirm->execute($r1, $pmUser);

    // Second receipt: +30 to same batch
    $r2 = $submit->execute([
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'IDEM_A2',
        'lines' => [array_merge($line, ['quantity' => 30])],
    ], $pmUser);
    $confirm->execute($r2, $pmUser);

    $batch = Batch::where('batch_no', 'REUSED_BATCH')->first();
    $stock = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->first();

    expect($stock->quantity)->toBe(50);
});

test('inactive product is refused (E2)', function () {
    $product = makeTestProduct(['is_active' => false]);
    $submit = new SubmitStockReceipt;

    expect(fn () => $submit->execute([
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'IDEM_INACTIVE',
        'lines' => [
            [
                'product_id' => $product->id,
                'batch_no' => 'B_INACTIVE',
                'expiry_date' => now()->addMonths(6)->format('Y-m-d'),
                'quantity' => 10,
            ],
        ],
    ], pm()))->toThrow(ValidationException::class);
});

test('double submit with same idempotency key creates only one receipt (AC-20)', function () {
    $product = makeTestProduct();
    $submit = new SubmitStockReceipt;

    $payload = [
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'IDEM_REPEAT_123',
        'lines' => [
            [
                'product_id' => $product->id,
                'batch_no' => 'B_REPEAT',
                'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
                'quantity' => 15,
            ],
        ],
    ];

    $r1 = $submit->execute($payload, pm());
    $r2 = $submit->execute($payload, pm());

    expect($r1->id)->toBe($r2->id)
        ->and(StockReceipt::where('idempotency_key', 'IDEM_REPEAT_123')->count())->toBe(1);
});

test('posted receipt cannot be posted again', function () {
    $product = makeTestProduct();
    $pmUser = pm();
    $submit = new SubmitStockReceipt;
    $confirm = new ConfirmStockReceipt;

    $receipt = $submit->execute([
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'IDEM_POSTED',
        'lines' => [
            [
                'product_id' => $product->id,
                'batch_no' => 'B_POSTED',
                'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
                'quantity' => 10,
            ],
        ],
    ], $pmUser);

    $confirm->execute($receipt, $pmUser);

    expect(fn () => $confirm->execute($receipt, $pmUser))->toThrow(ValidationException::class);
});
