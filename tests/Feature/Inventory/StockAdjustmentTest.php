<?php

use App\Actions\Inventory\RecordRecount;
use App\Actions\Inventory\RequestStockAdjustment;
use App\Enums\Role as RoleEnum;
use App\Exceptions\ApprovalException;
use App\Livewire\Inventory\Adjustments\Create;
use App\Livewire\Inventory\Adjustments\Index;
use App\Models\AdjustmentRequest;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ControlledDrugRegister;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\ApprovalService;
use App\Services\SettingsRepository;
use App\Services\StockMovementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    // Default threshold of 1000 ETB
    app(SettingsRepository::class)->put('inventory.adjustment_value_threshold', 1000);
    app(SettingsRepository::class)->put('pharmacy.handles_controlled_drugs', true);
});

function createProductAndBatch(array $productAttrs = [], array $batchAttrs = []): array
{
    $product = Product::create(array_merge([
        'brand' => 'Paracetamol 500mg',
        'generic_name' => 'Paracetamol',
        'strength' => '500mg',
        'dosage_form' => 'tablet',
        'is_active' => true,
        'classification' => 'otc',
    ], $productAttrs));

    $batch = Batch::create(array_merge([
        'product_id' => $product->id,
        'batch_no' => 'BCH-1001',
        'unit_cost' => 500, // 5.00 ETB = 500 santim
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'supplier_name' => 'Ethiopian Pharmaceuticals Supply Service',
    ], $batchAttrs));

    return [$product, $batch];
}

test('staff requests stock adjustment, PM approves, and ledger reflects adjustment (AC-08)', function () {
    [$product, $batch] = createProductAndBatch();
    $staff = ps();
    $pm = pm();

    // Initial stock receipt: 50 units
    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');
    expect($batch->sellableStockQuantity())->toBe(50);

    // Staff requests adjustment: 10 units damaged (-10 units, cost = 50 ETB, below 1000 ETB threshold)
    $action = app(RequestStockAdjustment::class);
    $adj = $action->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_DAMAGE,
        'quantity' => 10,
        'written_reason' => 'Broken bottles found during morning cleaning',
    ]);

    expect($adj)->not->toBeNull()
        ->and($adj->quantity)->toBe(-10)
        ->and($adj->status)->toBe(AdjustmentRequest::STATUS_PENDING_APPROVAL)
        ->and($adj->approval_request_id)->not->toBeNull();

    // Stock on hand remains 50 while pending
    expect($batch->fresh()->sellableStockQuantity())->toBe(50);

    $approvalRequest = $adj->approvalRequest;
    expect($approvalRequest->type)->toBe('stock.adjustment')
        ->and($approvalRequest->eligible_roles)->toContain(RoleEnum::PharmacyManager->value);

    // PM approves with reauth
    reauth();
    actAs($pm);
    app(ApprovalService::class)->approve($approvalRequest, $pm, 'Approved after verifying damaged bottles');

    // Ledger shows the adjustment movement
    $adj->refresh();
    expect($adj->status)->toBe(AdjustmentRequest::STATUS_APPROVED);

    $latestMovement = StockMovement::where('batch_id', $batch->id)->latest('id')->first();
    expect($latestMovement)->not->toBeNull()
        ->and($latestMovement->type)->toBe('adjustment')
        ->and($latestMovement->quantity)->toBe(-10)
        ->and($latestMovement->balance_after)->toBe(40);

    // Batch stock updated
    expect($batch->fresh()->sellableStockQuantity())->toBe(40);

    // Central immutable audit log recorded
    expect(AuditLog::where('action', 'stock.adjustment.approved')->count())->toBe(1);
});

test('found stock increases inventory when approved', function () {
    [$product, $batch] = createProductAndBatch();
    $staff = ps();
    $pm = pm();

    StockMovementService::post($batch, 20, 'receipt', $product, $pm, 'sellable');

    $adj = app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_FOUND_STOCK,
        'quantity' => 5,
        'written_reason' => 'Found unopened box in storage shelf B',
    ]);

    expect($adj->quantity)->toBe(5);

    reauth();
    actAs($pm);
    app(ApprovalService::class)->approve($adj->approvalRequest, $pm, 'Count verified');

    expect($batch->fresh()->sellableStockQuantity())->toBe(25);
    $latestMovement = StockMovement::where('batch_id', $batch->id)->latest('id')->first();
    expect($latestMovement->quantity)->toBe(5)
        ->and($latestMovement->balance_after)->toBe(25);
});

test('initiator cannot approve their own adjustment request (E1)', function () {
    [$product, $batch] = createProductAndBatch();
    $staff = ps();

    StockMovementService::post($batch, 50, 'receipt', $product, $staff, 'sellable');

    $adj = app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_LOSS,
        'quantity' => 5,
        'written_reason' => 'Missing units from tray',
    ]);

    reauth();
    actAs($staff);

    expect(fn () => app(ApprovalService::class)->approve($adj->approvalRequest, $staff))
        ->toThrow(ApprovalException::class);

    expect($adj->fresh()->status)->toBe(AdjustmentRequest::STATUS_PENDING_APPROVAL);
    expect($batch->fresh()->sellableStockQuantity())->toBe(50);
});

test('rejection leaves stock unchanged and notifies requester (A1)', function () {
    [$product, $batch] = createProductAndBatch();
    $staff = ps();
    $pm = pm();

    StockMovementService::post($batch, 30, 'receipt', $product, $pm, 'sellable');

    $adj = app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_LOSS,
        'quantity' => 5,
        'written_reason' => 'Suspected misplaced units',
    ]);

    reauth();
    actAs($pm);
    app(ApprovalService::class)->reject($adj->approvalRequest, $pm, 'Items were misplaced in shelf C, not lost.');

    $adj->refresh();
    expect($adj->status)->toBe(AdjustmentRequest::STATUS_REJECTED);
    expect($batch->fresh()->sellableStockQuantity())->toBe(30);

    // Requester received notification
    $notif = DB::table('notifications')
        ->where('notifiable_id', $staff->id)
        ->latest('id')
        ->first();
    expect($notif)->not->toBeNull()
        ->and($notif->type)->toBe('approval.rejected');
});

test('unapproved request expires with no stock change (E3)', function () {
    [$product, $batch] = createProductAndBatch();
    $staff = ps();
    $pm = pm();

    StockMovementService::post($batch, 30, 'receipt', $product, $pm, 'sellable');

    $adj = app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_DAMAGE,
        'quantity' => 5,
        'written_reason' => 'Damaged carton',
    ]);

    // Fast-forward past expiry
    $this->travel(4)->days();

    $expiredCount = app(ApprovalService::class)->expireOverdue();
    expect($expiredCount)->toBeGreaterThan(0);

    $adj->refresh();
    expect($adj->status)->toBe(AdjustmentRequest::STATUS_EXPIRED);
    expect($batch->fresh()->sellableStockQuantity())->toBe(30);
});

test('adjustment cannot make stock negative (E2)', function () {
    [$product, $batch] = createProductAndBatch();
    $staff = ps();
    $pm = pm();

    StockMovementService::post($batch, 10, 'receipt', $product, $pm, 'sellable');

    // Attempting to deduct 15 units when only 10 available
    expect(fn () => app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_DAMAGE,
        'quantity' => 15,
        'written_reason' => 'Water damage',
    ]))->toThrow(ValidationException::class);

    expect($batch->fresh()->sellableStockQuantity())->toBe(10);
});

test('recount required above threshold or when threshold is not set (BR-07)', function () {
    [$product, $batch] = createProductAndBatch([], [
        'unit_cost' => 10000, // 100.00 ETB each
    ]);
    $staff = ps();
    $pm = pm();

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');

    // Set threshold to 200 ETB
    app(SettingsRepository::class)->put('inventory.adjustment_value_threshold', 200);

    // Adjustment of 3 units = 300 ETB (> 200 ETB threshold)
    $adj = app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_EXPIRY_WRITE_OFF,
        'quantity' => 3,
        'written_reason' => 'Expired ampoules found in back shelf',
    ]);

    expect($adj->requiresRecount())->toBeTrue()
        ->and($adj->recount_done)->toBeFalse();

    // Approving without recount throws ApprovalException
    reauth();
    actAs($pm);
    expect(fn () => app(ApprovalService::class)->approve($adj->approvalRequest, $pm, 'Approved'))
        ->toThrow(ApprovalException::class);

    expect($adj->fresh()->status)->toBe(AdjustmentRequest::STATUS_PENDING_APPROVAL);

    // Conduct recount
    app(RecordRecount::class)->execute($pm, $adj, 'Physical count re-checked, verified 3 expired units.');
    expect($adj->fresh()->recount_done)->toBeTrue();

    // Now approval succeeds
    reauth();
    app(ApprovalService::class)->approve($adj->approvalRequest->fresh(), $pm, 'Approved after recount');

    expect($adj->fresh()->status)->toBe(AdjustmentRequest::STATUS_APPROVED);
    expect($batch->fresh()->sellableStockQuantity())->toBe(47);
});

test('recount required when threshold is null (not set)', function () {
    [$product, $batch] = createProductAndBatch([], [
        'unit_cost' => 100, // 1.00 ETB
    ]);
    $staff = ps();
    $pm = pm();

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');

    // Set threshold to null ("Not set")
    app(SettingsRepository::class)->put('inventory.adjustment_value_threshold', null);

    $adj = app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_DAMAGE,
        'quantity' => 1,
        'written_reason' => 'Cracked vial',
    ]);

    expect($adj->requiresRecount())->toBeTrue();

    reauth();
    actAs($pm);
    expect(fn () => app(ApprovalService::class)->approve($adj->approvalRequest, $pm))
        ->toThrow(ApprovalException::class);
});

test('Owner approves a PM request (A2)', function () {
    [$product, $batch] = createProductAndBatch();
    $pm = pm();
    $owner = owner();

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');

    actAs($pm);
    $adj = app(RequestStockAdjustment::class)->execute($pm, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_DAMAGE,
        'quantity' => 5,
        'written_reason' => 'Inventory count discrepancy adjustment',
    ]);

    // PM initiated -> eligible roles is ONLY Owner
    expect($adj->approvalRequest->eligible_roles)->toBe([RoleEnum::Owner->value]);

    // Another PM cannot approve
    $pm2 = pm();
    actAs($pm2);
    reauth();
    expect(fn () => app(ApprovalService::class)->approve($adj->approvalRequest, $pm2))
        ->toThrow(ApprovalException::class);

    // Owner approves successfully
    actAs($owner);
    reauth();
    app(ApprovalService::class)->approve($adj->approvalRequest, $owner, 'Owner signed off');

    expect($adj->fresh()->status)->toBe(AdjustmentRequest::STATUS_APPROVED);
    expect($batch->fresh()->sellableStockQuantity())->toBe(45);
});

test('controlled drug adjustment requires a second named person (UC-22 rule / STEP 5)', function () {
    [$product, $batch] = createProductAndBatch([
        'classification' => 'controlled',
    ]);
    $staff = ps();
    $witness = ps();
    $pm = pm();

    StockMovementService::post($batch, 20, 'receipt', $product, $pm, 'sellable', secondPerson: $witness);

    // Requesting without second person fails
    expect(fn () => app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_DAMAGE,
        'quantity' => 2,
        'written_reason' => 'Broken ampoule',
    ]))->toThrow(ValidationException::class);

    // Requesting with same user as second person fails
    expect(fn () => app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_DAMAGE,
        'quantity' => 2,
        'written_reason' => 'Broken ampoule',
        'second_person_id' => $staff->id,
    ]))->toThrow(ValidationException::class);

    // Requesting with distinct second person succeeds
    $adj = app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_DAMAGE,
        'quantity' => 2,
        'written_reason' => 'Broken ampoule during inventory transfer',
        'second_person_id' => $witness->id,
    ]);

    expect($adj->second_person_id)->toBe($witness->id);

    // Approval records in controlled drug register with second person
    reauth();
    actAs($pm);
    app(ApprovalService::class)->approve($adj->approvalRequest, $pm, 'Approved damage write-off');

    $registerEntry = ControlledDrugRegister::where('stock_movement_id', '!=', null)
        ->latest('id')
        ->first();

    expect($registerEntry)->not->toBeNull()
        ->and($registerEntry->entry_type)->toBe('adjustment')
        ->and($registerEntry->quantity)->toBe(2)
        ->and($registerEntry->running_balance)->toBe(18)
        ->and($registerEntry->second_person_id)->toBe($witness->id);
});

test('system admin cannot request or view adjustments', function () {
    $sa = sa();
    [$product, $batch] = createProductAndBatch();

    actAs($sa);
    $this->get(route('inventory.adjustments.index'))->assertForbidden();
    $this->get(route('inventory.adjustments.create'))->assertForbidden();

    expect(fn () => app(RequestStockAdjustment::class)->execute($sa, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_DAMAGE,
        'quantity' => 1,
        'written_reason' => 'SA attempt',
    ]))->toThrow(AuthorizationException::class);
});

test('Livewire Create component submits request and redirects', function () {
    [$product, $batch] = createProductAndBatch();
    $staff = ps();
    $pm = pm();

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');

    actAs($staff);

    Livewire::test(Create::class)
        ->set('product_search', 'Paracetamol')
        ->call('selectProduct', $product->id)
        ->set('batch_id', $batch->id)
        ->set('reason_type', AdjustmentRequest::REASON_DAMAGE)
        ->set('quantity', 4)
        ->set('written_reason', 'Water spilled on packaging')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('inventory.adjustments.index'));

    expect(AdjustmentRequest::count())->toBe(1);
    $adj = AdjustmentRequest::first();
    expect($adj->quantity)->toBe(-4)
        ->and($adj->status)->toBe(AdjustmentRequest::STATUS_PENDING_APPROVAL);
});

test('Livewire Index component lists adjustments and performs recount', function () {
    [$product, $batch] = createProductAndBatch([], ['unit_cost' => 10000]);
    $staff = ps();
    $pm = pm();

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');
    app(SettingsRepository::class)->put('inventory.adjustment_value_threshold', 100); // threshold 100 ETB

    $adj = app(RequestStockAdjustment::class)->execute($staff, [
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_LOSS,
        'quantity' => 2,
        'written_reason' => 'Discrepancy noted',
    ]);

    actAs($pm);

    Livewire::test(Index::class)
        ->assertSee('Paracetamol 500mg')
        ->assertSee('BCH-1001')
        ->call('openRecountModal', $adj->id)
        ->set('recountNotes', 'Physical count matched 2 missing units.')
        ->call('submitRecount')
        ->assertHasNoErrors()
        ->assertSee(__('catalogue.recount_recorded_success'));

    expect($adj->fresh()->recount_done)->toBeTrue();
});
