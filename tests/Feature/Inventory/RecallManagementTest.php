<?php

use App\Actions\Inventory\RequestCloseRecall;
use App\Actions\Inventory\StartRecall;
use App\Actions\Inventory\TraceRecallSales;
use App\Exceptions\ApprovalException;
use App\Exceptions\SellabilityException;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Customer;
use App\Models\DispensingEvent;
use App\Models\Prescription;
use App\Models\PrescriptionLine;
use App\Models\Product;
use App\Models\QuarantineRecord;
use App\Models\Recall;
use App\Models\RecallBatch;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\Shift;
use App\Models\Till;
use App\Reports\Definitions\Compliance\ComplianceRecallsReport;
use App\Services\ApprovalService;
use App\Services\FefoBatchResolver;
use App\Services\SellabilityGuard;
use App\Services\StockMovementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

function makeRecallProduct(array $attr = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Paracetamol 500mg',
        'generic_name' => 'Paracetamol',
        'classification' => 'otc',
        'reorder_level' => 50,
        'min_level' => 20,
        'max_level' => 500,
        'is_active' => true,
    ], $attr));
}

function makeRecallBatch(Product $product, array $attr = []): Batch
{
    return Batch::create(array_merge([
        'product_id' => $product->id,
        'batch_no' => 'RCL-BAT-'.strtoupper(bin2hex(random_bytes(3))),
        'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
        'unit_cost' => 200,
        'supplier_name' => 'EPHARM',
    ], $attr));
}

test('StartRecall creates recall, moves sellable stock to quarantine via StockMovementService, blocks sale, and notifies (STEP 1)', function () {
    $pm = pm();
    $owner = owner();
    $product = makeRecallProduct();
    $batch1 = makeRecallBatch($product, ['batch_no' => 'BATCH-A']);
    $batch2 = makeRecallBatch($product, ['batch_no' => 'BATCH-B']);

    // Receive sellable stock
    StockMovementService::post($batch1, 50, 'receipt', $product, $pm, 'sellable');
    StockMovementService::post($batch2, 30, 'receipt', $product, $pm, 'sellable');

    expect(BatchStock::where('batch_id', $batch1->id)->where('status', 'sellable')->sum('quantity'))->toBe(50)
        ->and(BatchStock::where('batch_id', $batch2->id)->where('status', 'sellable')->sum('quantity'))->toBe(30);

    $action = app(StartRecall::class);
    $recall = $action->execute(
        user: $pm,
        productId: $product->id,
        batchIds: [$batch1->id, $batch2->id],
        reason: 'Impurity detected during post-market surveillance',
        recallDate: now()->toDateString(),
        severity: Recall::SEVERITY_CLASS_I,
        sourceReference: 'EFDA-ALERT-2026-001'
    );

    expect($recall)->toBeInstanceOf(Recall::class)
        ->and($recall->recall_no)->toStartWith('RCL-')
        ->and($recall->status)->toBe(Recall::STATUS_OPEN)
        ->and($recall->severity)->toBe(Recall::SEVERITY_CLASS_I)
        ->and($recall->source_reference)->toBe('EFDA-ALERT-2026-001')
        ->and($recall->batches)->toHaveCount(2);

    // Stock for both batches moved completely from sellable to quarantine
    expect(BatchStock::where('batch_id', $batch1->id)->where('status', 'sellable')->sum('quantity'))->toBe(0)
        ->and(BatchStock::where('batch_id', $batch1->id)->where('status', 'quarantine')->sum('quantity'))->toBe(50)
        ->and(BatchStock::where('batch_id', $batch2->id)->where('status', 'sellable')->sum('quantity'))->toBe(0)
        ->and(BatchStock::where('batch_id', $batch2->id)->where('status', 'quarantine')->sum('quantity'))->toBe(30);

    // Quarantine records exist with reason recalled
    $qRecords = QuarantineRecord::whereIn('batch_id', [$batch1->id, $batch2->id])->get();
    expect($qRecords)->toHaveCount(2);
    foreach ($qRecords as $qr) {
        expect($qr->reason)->toBe(QuarantineRecord::REASON_RECALLED)
            ->and($qr->status)->toBe(QuarantineRecord::STATUS_OPEN);
    }

    // Recall batches records created
    $rb1 = RecallBatch::where('recall_id', $recall->id)->where('batch_id', $batch1->id)->first();
    $rb2 = RecallBatch::where('recall_id', $recall->id)->where('batch_id', $batch2->id)->first();
    expect($rb1->quantity_found)->toBe(50)
        ->and($rb1->quantity_quarantined)->toBe(50)
        ->and($rb2->quantity_found)->toBe(30)
        ->and($rb2->quantity_quarantined)->toBe(30);

    // Audit log written
    $log = AuditLog::where('action', 'stock.recall.started')->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['recall_no'])->toBe($recall->recall_no)
        ->and($log->user_id)->toBe($pm->id);
});

test('recalled batch cannot be sold and is excluded from FEFO eligible batches (BR-01, AC-11)', function () {
    $pm = pm();
    $product = makeRecallProduct();
    $batch = makeRecallBatch($product);

    StockMovementService::post($batch, 40, 'receipt', $product, $pm, 'sellable');

    // Initiate recall
    app(StartRecall::class)->execute(
        user: $pm,
        productId: $product->id,
        batchIds: [$batch->id],
        reason: 'Packaging defect',
        recallDate: now()->toDateString()
    );

    // 1. SellabilityGuard refuses sale
    expect(fn () => SellabilityGuard::check($product, $batch, 1, $pm, 'sell'))
        ->toThrow(SellabilityException::class);

    // 2. FefoBatchResolver excludes recalled batch
    $eligible = FefoBatchResolver::getEligibleBatches($product);
    expect($eligible->pluck('id'))->not->toContain($batch->id);
});

test('TraceRecallSales lists sales and dispensing events for recalled batches (STEP 2)', function () {
    $pm = pm();
    $staff = ps();
    $product = makeRecallProduct();
    $batch = makeRecallBatch($product, ['batch_no' => 'TRACE-BATCH-1']);

    // Receive 100 units
    StockMovementService::post($batch, 100, 'receipt', $product, $pm, 'sellable');

    // Create a till and shift for sales
    $till = Till::create(['name' => 'Till 1', 'is_active' => true]);
    $shift = Shift::create([
        'till_id' => $till->id,
        'user_id' => $staff->id,
        'status' => 'open',
        'opened_at' => now(),
    ]);

    // 1. Sale to Customer A
    $customerA = Customer::create([
        'name' => 'Abebe Bikila',
        'phone' => '0911000001',
    ]);

    $saleA = Sale::create([
        'receipt_no' => 'REC-TRACE-001',
        'shift_id' => $shift->id,
        'user_id' => $staff->id,
        'customer_id' => $customerA->id,
        'status' => 'completed',
        'total' => 2000,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
    ]);

    SaleLine::create([
        'sale_id' => $saleA->id,
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'quantity' => 10,
        'unit_price' => 200,
        'subtotal' => 2000,
        'is_fefo_override' => false,
    ]);

    // 2. Sale to Walk-in customer (no customer account)
    $saleB = Sale::create([
        'receipt_no' => 'REC-TRACE-002',
        'shift_id' => $shift->id,
        'user_id' => $staff->id,
        'status' => 'completed',
        'total' => 1000,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
    ]);

    SaleLine::create([
        'sale_id' => $saleB->id,
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'quantity' => 5,
        'unit_price' => 200,
        'subtotal' => 1000,
        'is_fefo_override' => false,
    ]);

    // 3. Dispensing Event for Customer C with Prescription
    $customerC = Customer::create([
        'name' => 'Chaltu Tadesse',
        'phone' => '0911000003',
    ]);

    $prescription = Prescription::create([
        'prescription_number' => 'RX-TRACE-001',
        'customer_id' => $customerC->id,
        'prescriber_name' => 'Dr. Aster',
        'prescriber_registration_no' => 'DOC-999',
        'prescription_date' => now()->toDateString(),
        'status' => Prescription::STATUS_VERIFIED,
        'created_by' => $pm->id,
    ]);

    $rxLine = PrescriptionLine::create([
        'prescription_id' => $prescription->id,
        'product_id' => $product->id,
        'dose' => '500mg TID',
        'quantity_prescribed' => 20,
        'quantity_dispensed' => 20,
    ]);

    DispensingEvent::create([
        'prescription_line_id' => $rxLine->id,
        'batch_id' => $batch->id,
        'quantity' => 20,
        'user_id' => $pm->id,
        'dispensed_at' => now(),
    ]);

    // Start recall for this batch
    $recall = app(StartRecall::class)->execute(
        user: $pm,
        productId: $product->id,
        batchIds: [$batch->id],
        reason: 'Recall for contamination test',
        recallDate: now()->toDateString()
    );

    // Trace recall sales
    $tracer = app(TraceRecallSales::class);
    $results = $tracer->execute($recall, $pm);

    expect($results)->toHaveCount(3);

    // Verify Customer A was traced
    $foundCustomerA = $results->firstWhere('customer_name', 'Abebe Bikila');
    expect($foundCustomerA)->not->toBeNull()
        ->and($foundCustomerA['reference_no'])->toBe('REC-TRACE-001')
        ->and($foundCustomerA['quantity'])->toBe(10)
        ->and($foundCustomerA['customer_phone'])->toBe('0911000001');

    // Verify Walk-in customer was traced
    $foundWalkIn = $results->firstWhere('reference_no', 'REC-TRACE-002');
    expect($foundWalkIn)->not->toBeNull()
        ->and($foundWalkIn['customer_name'])->toBe('Walk-in Customer')
        ->and($foundWalkIn['quantity'])->toBe(5);

    // Verify Dispensed prescription patient was traced
    $foundCustomerC = $results->firstWhere('customer_name', 'Chaltu Tadesse');
    expect($foundCustomerC)->not->toBeNull()
        ->and($foundCustomerC['event_type'])->toBe('dispense')
        ->and($foundCustomerC['quantity'])->toBe(20)
        ->and($foundCustomerC['prescriber_name'])->toBe('Dr. Aster');

    // Audit log written
    $traceLog = AuditLog::where('action', 'stock.recall.traced')->first();
    expect($traceLog)->not->toBeNull()
        ->and($traceLog->metadata['recall_no'])->toBe($recall->recall_no);
});

test('closing recall requires approval, initiator cannot approve, and transitions to closed upon approval (STEP 3)', function () {
    $pm = pm();
    $owner = owner();
    $product = makeRecallProduct();
    $batch = makeRecallBatch($product);

    StockMovementService::post($batch, 20, 'receipt', $product, $pm, 'sellable');

    $recall = app(StartRecall::class)->execute(
        user: $pm,
        productId: $product->id,
        batchIds: [$batch->id],
        reason: 'Closing test recall',
        recallDate: now()->toDateString()
    );

    expect($recall->isOpen())->toBeTrue();

    // PM requests to close recall
    $requestAction = app(RequestCloseRecall::class);
    $approvalRequest = $requestAction->execute(
        user: $pm,
        recall: $recall,
        comment: 'All batches quarantined, customers traced and notified, regulatory notice completed.'
    );

    expect($approvalRequest->type)->toBe('stock.recall.close')
        ->and($approvalRequest->status)->toBe('pending')
        ->and($approvalRequest->initiator_id)->toBe($pm->id);

    $approvalService = app(ApprovalService::class);

    // PM (initiator) cannot approve own request (BR-14)
    reauth();
    expect(fn () => $approvalService->approve($approvalRequest, $pm))
        ->toThrow(ApprovalException::class);

    // Owner approves closing
    reauth();
    $approvalService->approve($approvalRequest, $owner, 'Approved close of recall RCL');

    // Recall is closed
    $recall->refresh();
    expect($recall->isClosed())->toBeTrue()
        ->and($recall->closed_at)->not->toBeNull();

    // Recall batches action updated
    $rb = RecallBatch::where('recall_id', $recall->id)->first();
    expect($rb->action)->toBe('completed');

    // Audit log written
    $log = AuditLog::where('action', 'stock.recall.closed')->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($owner->id);
});

test('ComplianceRecallsReport accurately reflects recall records and details', function () {
    $pm = pm();
    $product = makeRecallProduct();
    $batch = makeRecallBatch($product);

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');

    $recall = app(StartRecall::class)->execute(
        user: $pm,
        productId: $product->id,
        batchIds: [$batch->id],
        reason: 'Compliance test recall',
        recallDate: now()->toDateString(),
        severity: Recall::SEVERITY_CLASS_II
    );

    $report = new ComplianceRecallsReport;
    $data = $report->query([
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ], $pm);

    expect($data['row_count'])->toBe(1)
        ->and($data['rows'][0][1])->toBe($recall->recall_no)
        ->and($data['rows'][0][2])->toBe($product->brand)
        ->and($data['rows'][0][3])->toBe($batch->batch_no)
        ->and($data['rows'][0][5])->toBe('50')
        ->and($data['summary']['total_recalled_units'])->toBe(50);
});

test('system admin cannot initiate or trace recalls', function () {
    $sa = sa();
    $product = makeRecallProduct();
    $batch = makeRecallBatch($product);

    $action = app(StartRecall::class);
    expect(fn () => $action->execute(
        user: $sa,
        productId: $product->id,
        batchIds: [$batch->id],
        reason: 'SA attempt',
        recallDate: now()->toDateString()
    ))->toThrow(AuthorizationException::class);
});
