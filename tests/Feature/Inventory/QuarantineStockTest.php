<?php

use App\Actions\Inventory\QuarantineStock;
use App\Actions\Inventory\RequestQuarantineRelease;
use App\Contracts\ExpiredBatchQuarantiner;
use App\Exceptions\ApprovalException;
use App\Exceptions\SellabilityException;
use App\Livewire\Quarantine\Index;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\QuarantineRecord;
use App\Models\StockMovement;
use App\Services\ApprovalService;
use App\Services\ExpiredBatchQuarantinerService;
use App\Services\SellabilityGuard;
use App\Services\StockMovementService;
use App\Services\StockOnHandQuery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function makeQuarantineTestProduct(array $attr = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'classification' => 'rx',
        'reorder_level' => 20,
        'min_level' => 10,
        'max_level' => 100,
        'is_active' => true,
    ], $attr));
}

function makeQuarantineBatch(Product $product, array $attr = []): Batch
{
    return Batch::create(array_merge([
        'product_id' => $product->id,
        'batch_no' => 'AMX-'.strtoupper(bin2hex(random_bytes(3))),
        'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
        'unit_cost' => 500, // 5.00 ETB
        'supplier_name' => 'Ethiopian Pharmaceuticals Supply Service',
    ], $attr));
}

test('manual quarantine moves stock and writes the ledger (AC-07)', function () {
    $product = makeQuarantineTestProduct();
    $batch = makeQuarantineBatch($product);
    $pm = pm();
    $staff = ps();

    // Initial receipt: 100 sellable units
    StockMovementService::post($batch, 100, 'receipt', $product, $pm, 'sellable');

    expect(BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity'))->toBe(100)
        ->and(BatchStock::where('batch_id', $batch->id)->where('status', 'quarantine')->sum('quantity'))->toBe(0);

    $action = app(QuarantineStock::class);
    $record = $action->execute(
        batch: $batch,
        quantity: 30,
        reason: QuarantineRecord::REASON_DAMAGED,
        note: 'Damaged during shelf reorganization',
        actor: $staff
    );

    expect($record->status)->toBe('open')
        ->and($record->quantity)->toBe(30)
        ->and($record->reason)->toBe('damaged')
        ->and($record->created_by)->toBe($staff->id);

    // Stock balances updated
    $sellableStock = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->first();
    $quarantineStock = BatchStock::where('batch_id', $batch->id)->where('status', 'quarantine')->first();

    expect($sellableStock->quantity)->toBe(70)
        ->and($quarantineStock->quantity)->toBe(30);

    // Ledger movements recorded (deduct 30 from sellable, add 30 to quarantine)
    $movements = StockMovement::where('batch_id', $batch->id)->where('type', 'quarantine')->get();
    expect($movements)->toHaveCount(2);

    $deduction = $movements->firstWhere('quantity', -30);
    $addition = $movements->firstWhere('quantity', 30);

    expect($deduction->stock_status)->toBe('sellable')
        ->and($deduction->balance_after)->toBe(70)
        ->and($addition->stock_status)->toBe('quarantine')
        ->and($addition->balance_after)->toBe(30);

    // Audit log written
    $log = AuditLog::where('action', 'stock.quarantine.created')->first();
    expect($log)->not->toBeNull()
        ->and($log->metadata['quantity'])->toBe(30)
        ->and($log->metadata['reason'])->toBe('damaged')
        ->and($log->user_id)->toBe($staff->id);
});

test('quarantine refuses excess quantity beyond sellable stock', function () {
    $product = makeQuarantineTestProduct();
    $batch = makeQuarantineBatch($product);
    $pm = pm();

    StockMovementService::post($batch, 20, 'receipt', $product, $pm, 'sellable');

    $action = app(QuarantineStock::class);

    expect(fn () => $action->execute($batch, 25, QuarantineRecord::REASON_DAMAGED, null, $pm))
        ->toThrow(ValidationException::class);
});

test('expiry command quarantines automatically with system user and batch cannot be sold (AC-07)', function () {
    $product = makeQuarantineTestProduct();
    // Batch expired yesterday
    $batch = makeQuarantineBatch($product, [
        'expiry_date' => now()->subDay()->format('Y-m-d'),
    ]);
    $pm = pm();

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');

    expect(BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity'))->toBe(50);

    // Run command
    Artisan::call('batches:quarantine-expired');

    // Sellable stock moved to quarantine
    expect(BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity'))->toBe(0)
        ->and(BatchStock::where('batch_id', $batch->id)->where('status', 'quarantine')->sum('quantity'))->toBe(50);

    $record = QuarantineRecord::where('batch_id', $batch->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->reason)->toBe('expired')
        ->and($record->quantity)->toBe(50);

    // System user recorded in audit
    $log = AuditLog::where('action', 'stock.quarantine.created')
        ->where('auditable_id', (string) $record->id)
        ->first();
    expect($log)->not->toBeNull();

    // Sellability check blocked because it is expired and quarantined
    expect(fn () => SellabilityGuard::check($product, $batch, 1, $pm, 'sell'))
        ->toThrow(SellabilityException::class);
});

test('release needs PM approval and initiator cannot approve (UC-34 / AC-07)', function () {
    $product = makeQuarantineTestProduct();
    $batch = makeQuarantineBatch($product);
    $pm = pm();
    $staff = ps();

    // Receipt and quarantine
    StockMovementService::post($batch, 40, 'receipt', $product, $pm, 'sellable');
    $record = app(QuarantineStock::class)->execute($batch, 40, QuarantineRecord::REASON_DAMAGED, 'Suspected damage', $staff);

    // Staff requests release
    $releaseAction = app(RequestQuarantineRelease::class);
    $approvalRequest = $releaseAction->execute($record, 'Packaging inspection cleared - no damage to vials', $staff);

    expect($approvalRequest->status)->toBe('pending')
        ->and($approvalRequest->type)->toBe('stock.quarantine.release')
        ->and($approvalRequest->initiator_id)->toBe($staff->id);

    $approvalService = app(ApprovalService::class);

    // Initiator cannot approve their own request
    reauth();
    expect(fn () => $approvalService->approve($approvalRequest, $staff))
        ->toThrow(ApprovalException::class);

    // PM approves
    reauth();
    $approvalService->approve($approvalRequest, $pm, 'Approved after laboratory check');

    // Record is marked released
    expect($record->refresh()->status)->toBe('released');

    // Stock returned to sellable
    expect(BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity'))->toBe(40)
        ->and(BatchStock::where('batch_id', $batch->id)->where('status', 'quarantine')->sum('quantity'))->toBe(0);

    // Audit trail logs release
    $releaseLog = AuditLog::where('action', 'stock.quarantine.released')->first();
    expect($releaseLog)->not->toBeNull()
        ->and($releaseLog->user_id)->toBe($pm->id);
});

test('recalled batch cannot be released', function () {
    $product = makeQuarantineTestProduct();
    $batch = makeQuarantineBatch($product);
    $pm = pm();
    $staff = ps();

    StockMovementService::post($batch, 30, 'receipt', $product, $pm, 'sellable');
    $record = app(QuarantineStock::class)->execute($batch, 30, QuarantineRecord::REASON_RECALLED, 'National regulatory recall notice', $staff);

    $releaseAction = app(RequestQuarantineRelease::class);

    // Action refuses release request on recalled batch
    expect(fn () => $releaseAction->execute($record, 'Mistake in classification', $staff))
        ->toThrow(ValidationException::class);
});

test('sale attempt on quarantined stock blocked and logged (E1)', function () {
    $product = makeQuarantineTestProduct();
    $batch = makeQuarantineBatch($product);
    $pm = pm();
    $staff = ps();

    // 20 units quarantined, 0 sellable
    StockMovementService::post($batch, 20, 'receipt', $product, $pm, 'sellable');
    app(QuarantineStock::class)->execute($batch, 20, QuarantineRecord::REASON_DAMAGED, 'Contamination test', $staff);

    // Attempt to sell
    try {
        SellabilityGuard::check($product, $batch, 5, $staff, 'sell');
        $this->fail('Expected SellabilityException was not thrown');
    } catch (SellabilityException $e) {
        expect($e->reason)->toBe('quarantined_stock');
    }

    // Check audit log for blocked attempt
    $blockedLog = AuditLog::where('action', 'stock.sellability.blocked')->first();
    expect($blockedLog)->not->toBeNull()
        ->and($blockedLog->metadata['batch_id'])->toBe($batch->id)
        ->and($blockedLog->metadata['quantity'])->toBe(5)
        ->and($blockedLog->metadata['action'])->toBe('sell')
        ->and($blockedLog->metadata['reason'])->toBe('quarantined_stock');
});

test('quarantine quantity excluded from sellable totals and valuation', function () {
    $product = makeQuarantineTestProduct();
    $batch = makeQuarantineBatch($product, ['unit_cost' => 1000]); // 10.00 ETB
    $pm = pm();
    $staff = ps();

    // 100 units received, 40 quarantined, 60 remain sellable
    StockMovementService::post($batch, 100, 'receipt', $product, $pm, 'sellable');
    app(QuarantineStock::class)->execute($batch, 40, QuarantineRecord::REASON_DAMAGED, null, $staff);

    $product->load(['batches.stocks']);

    $metrics = StockOnHandQuery::metrics($product, canViewCost: true);

    // total_sellable is 60, total_quarantine is 40
    expect($metrics['total_sellable'])->toBe(60)
        ->and($metrics['total_quarantine'])->toBe(40);

    // valuation is strictly calculated on sellable stock: 60 * 1000 = 60,000 minor units (600.00 ETB)
    expect($metrics['total_valuation'])->toBe(60000);
});

test('system admin cannot access quarantine route or actions', function () {
    $sa = sa();

    actAs($sa);

    $this->get(route('inventory.quarantine'))->assertForbidden();
});

test('ExpiredBatchQuarantiner interface resolves and functions properly', function () {
    $quarantiner = app(ExpiredBatchQuarantiner::class);
    expect($quarantiner)->toBeInstanceOf(ExpiredBatchQuarantinerService::class);

    $product = makeQuarantineTestProduct();
    $batch = makeQuarantineBatch($product, [
        'expiry_date' => now()->subDays(5)->format('Y-m-d'),
    ]);
    $pm = pm();

    StockMovementService::post($batch, 25, 'receipt', $product, $pm, 'sellable');

    $quarantiner->quarantine($batch);

    expect(BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity'))->toBe(0)
        ->and(BatchStock::where('batch_id', $batch->id)->where('status', 'quarantine')->sum('quantity'))->toBe(25);
});

test('livewire quarantine index component renders and functions', function () {
    $product = makeQuarantineTestProduct();
    $batch = makeQuarantineBatch($product);
    $pm = pm();
    $staff = ps();

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');

    actAs($staff);

    Livewire::test(Index::class)
        ->assertStatus(200)
        ->assertSee('Stock Quarantine Management')
        ->call('openQuarantineModal', $batch->id)
        ->set('selectedBatchId', $batch->id)
        ->set('quarantineQuantity', 10)
        ->set('quarantineReason', 'damaged')
        ->set('quarantineNote', 'Livewire test quarantine')
        ->call('submitQuarantine')
        ->assertHasNoErrors();

    expect(BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->sum('quantity'))->toBe(40)
        ->and(BatchStock::where('batch_id', $batch->id)->where('status', 'quarantine')->sum('quantity'))->toBe(10);
});
