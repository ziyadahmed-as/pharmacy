<?php

use App\Actions\Inventory\QuarantineStock;
use App\Actions\Inventory\RequestDisposal;
use App\Exceptions\ApprovalException;
use App\Livewire\Inventory\Disposals\Certificate;
use App\Livewire\Inventory\Disposals\Index;
use App\Livewire\Inventory\Disposals\Show;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\ControlledDrugRegister;
use App\Models\Disposal;
use App\Models\Product;
use App\Models\QuarantineRecord;
use App\Models\StockMovement;
use App\Services\ApprovalService;
use App\Services\SettingsRepository;
use App\Services\StockMovementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('pharmacy.handles_controlled_drugs', true);
});

function makeDisposalProduct(array $attr = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'classification' => 'rx',
        'is_active' => true,
        'reorder_level' => 10,
    ], $attr));
}

function makeDisposalBatch(Product $product, array $attr = []): Batch
{
    return Batch::create(array_merge([
        'product_id' => $product->id,
        'batch_no' => 'DSP-'.strtoupper(bin2hex(random_bytes(3))),
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 600, // 6.00 ETB
        'supplier_name' => 'EPSS Supplier',
    ], $attr));
}

test('only quarantined stock can be disposed (precondition)', function () {
    $staff = ps();
    $pm = pm();
    $product = makeDisposalProduct();
    $batch = makeDisposalBatch($product);

    // Initial stock receipt (sellable only)
    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');

    // Create a released / closed quarantine record
    $qr = QuarantineRecord::create([
        'batch_id' => $batch->id,
        'quantity' => 10,
        'reason' => QuarantineRecord::REASON_EXPIRED,
        'status' => QuarantineRecord::STATUS_RELEASED,
        'created_by' => $pm->id,
    ]);

    actAs($staff);
    $action = app(RequestDisposal::class);

    expect(fn () => $action->execute($staff, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 5,
        'reason' => 'expired',
        'method' => 'incineration',
    ]))->toThrow(ValidationException::class);
});

test('PM approval removes stock, writes record and certificate (AC-10)', function () {
    $staff = ps();
    $pm = pm();
    $product = makeDisposalProduct();
    $batch = makeDisposalBatch($product);

    // Initial receipt and quarantine
    StockMovementService::post($batch, 40, 'receipt', $product, $pm, 'sellable');
    $qr = app(QuarantineStock::class)->execute($batch, 25, QuarantineRecord::REASON_EXPIRED, 'Expired batch', $pm);

    expect($batch->quarantineStockQuantity())->toBe(25);

    // Staff requests disposal of 20 units
    actAs($staff);
    $action = app(RequestDisposal::class);
    $disposal = $action->execute($staff, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 20,
        'reason' => 'expired',
        'method' => 'incineration',
        'notes' => 'Disposed in certified incinerator',
    ]);

    expect($disposal->status)->toBe(Disposal::STATUS_PENDING)
        ->and($disposal->quantity)->toBe(20)
        ->and($disposal->approval_request_id)->not->toBeNull();

    // Stock in quarantine remains unchanged while pending
    expect($batch->fresh()->quarantineStockQuantity())->toBe(25);

    // PM approves request with password reauth
    reauth();
    actAs($pm);
    $approvalReq = $disposal->approvalRequest;
    app(ApprovalService::class)->approve($approvalReq, $pm, 'Approved for scheduled incineration');

    $disposal->refresh();
    expect($disposal->status)->toBe(Disposal::STATUS_APPROVED)
        ->and($disposal->approved_by)->toBe($pm->id)
        ->and($disposal->certificate_no)->not->toBeNull()
        ->and($disposal->certificate_no)->toStartWith('DISP-');

    // Quarantine stock is now reduced: 25 - 20 = 5 units
    expect($batch->fresh()->quarantineStockQuantity())->toBe(5);

    // Ledger shows disposal movement
    $movement = StockMovement::where('source_type', Disposal::class)
        ->where('source_id', $disposal->id)
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->type)->toBe('disposal')
        ->and($movement->quantity)->toBe(-20)
        ->and($movement->stock_status)->toBe('quarantine');

    // Audit log written
    $audit = AuditLog::where('action', 'stock.disposal.approved')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($pm->id);
});

test('initiator cannot approve their own disposal request (AC-10)', function () {
    $pm = pm();
    $owner = owner();
    $product = makeDisposalProduct();
    $batch = makeDisposalBatch($product);

    StockMovementService::post($batch, 30, 'receipt', $product, $pm, 'sellable');
    $qr = app(QuarantineStock::class)->execute($batch, 20, QuarantineRecord::REASON_DAMAGED, 'Water damaged', $pm);

    actAs($pm);
    $disposal = app(RequestDisposal::class)->execute($pm, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 15,
        'reason' => 'damaged',
        'method' => 'authorised_collector',
        'collector_name' => 'CleanCity Medical Waste',
    ]);

    $approvalReq = $disposal->approvalRequest;

    // PM cannot approve their own request (E1 / AC-10)
    reauth();
    expect(fn () => app(ApprovalService::class)->approve($approvalReq, $pm, 'Self approval attempt'))
        ->toThrow(ApprovalException::class);

    // Owner approves the PM request (A2)
    reauth();
    actAs($owner);
    app(ApprovalService::class)->approve($approvalReq, $owner, 'Owner approval of PM request');

    $disposal->refresh();
    expect($disposal->status)->toBe(Disposal::STATUS_APPROVED)
        ->and($disposal->approved_by)->toBe($owner->id);
});

test('controlled drug disposal without witness is refused (AC-10)', function () {
    $staff = ps();
    $pm = pm();
    $product = makeDisposalProduct(['classification' => 'controlled']);
    $batch = makeDisposalBatch($product);

    StockMovementService::post($batch, 20, 'receipt', $product, $pm, 'sellable');
    $qr = app(QuarantineStock::class)->execute($batch, 10, QuarantineRecord::REASON_EXPIRED, 'Expired morphine', $pm, secondPerson: $staff);

    actAs($staff);
    $action = app(RequestDisposal::class);

    // No witness specified
    expect(fn () => $action->execute($staff, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 5,
        'reason' => 'expired',
        'method' => 'incineration',
        'witness_id' => null,
    ]))->toThrow(ValidationException::class);
});

test('controlled drug disposal with witness equal to performer is refused (AC-10)', function () {
    $staff = ps();
    $pm = pm();
    $product = makeDisposalProduct(['classification' => 'controlled']);
    $batch = makeDisposalBatch($product);

    StockMovementService::post($batch, 20, 'receipt', $product, $pm, 'sellable');
    $qr = app(QuarantineStock::class)->execute($batch, 10, QuarantineRecord::REASON_EXPIRED, 'Expired', $pm, secondPerson: $staff);

    actAs($staff);
    $action = app(RequestDisposal::class);

    // Witness equal to performer (staff)
    expect(fn () => $action->execute($staff, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 5,
        'reason' => 'expired',
        'method' => 'incineration',
        'performer_id' => $staff->id,
        'witness_id' => $staff->id,
    ]))->toThrow(ValidationException::class);
});

test('approved controlled drug disposal writes register entry with witness co-signature', function () {
    $staff = ps();
    $pm = pm();
    $product = makeDisposalProduct(['classification' => 'controlled']);
    $batch = makeDisposalBatch($product);

    StockMovementService::post($batch, 30, 'receipt', $product, $pm, 'sellable');
    $qr = app(QuarantineStock::class)->execute($batch, 15, QuarantineRecord::REASON_EXPIRED, 'Expired controlled', $pm, secondPerson: $staff);

    actAs($staff);
    $disposal = app(RequestDisposal::class)->execute($staff, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 10,
        'reason' => 'expired',
        'method' => 'incineration',
        'performer_id' => $staff->id,
        'witness_id' => $pm->id, // PM as independent witness
    ]);

    reauth();
    $owner = owner();
    actAs($owner);
    app(ApprovalService::class)->approve($disposal->approvalRequest, $owner, 'Approved destruction with witness');

    $disposal->refresh();
    expect($disposal->status)->toBe(Disposal::STATUS_APPROVED);

    // Controlled Drug Register record exists with entry_type = disposal
    $reg = ControlledDrugRegister::where('batch_id', $batch->id)
        ->where('entry_type', 'disposal')
        ->first();

    expect($reg)->not->toBeNull()
        ->and($reg->quantity)->toBe(10)
        ->and($reg->user_id)->toBe($staff->id)
        ->and($reg->second_person_id)->toBe($pm->id);
});

test('rejection keeps stock in quarantine (A2)', function () {
    $staff = ps();
    $pm = pm();
    $product = makeDisposalProduct();
    $batch = makeDisposalBatch($product);

    StockMovementService::post($batch, 30, 'receipt', $product, $pm, 'sellable');
    $qr = app(QuarantineStock::class)->execute($batch, 18, QuarantineRecord::REASON_CONTAMINATED, 'Suspect batch', $pm);

    actAs($staff);
    $disposal = app(RequestDisposal::class)->execute($staff, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 10,
        'reason' => 'contaminated',
        'method' => 'incineration',
    ]);

    expect($batch->fresh()->quarantineStockQuantity())->toBe(18);

    // PM rejects request
    reauth();
    actAs($pm);
    app(ApprovalService::class)->reject($disposal->approvalRequest, $pm, 'Further investigation required before destruction');

    $disposal->refresh();
    expect($disposal->status)->toBe(Disposal::STATUS_REJECTED)
        ->and($disposal->rejected_by)->toBe($pm->id)
        ->and($disposal->rejection_reason)->toBe('Further investigation required before destruction');

    // Quarantine stock remains untouched
    expect($batch->fresh()->quarantineStockQuantity())->toBe(18);

    // No disposal movement posted
    expect(StockMovement::where('source_type', Disposal::class)->count())->toBe(0);
});

test('return to supplier reduces the specific batch and rejects quantity above the batch (AC-25)', function () {
    $staff = ps();
    $pm = pm();
    $product = makeDisposalProduct();
    $batch = makeDisposalBatch($product, ['supplier_name' => 'MedSupply Ethiopia']);

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');
    $qr = app(QuarantineStock::class)->execute($batch, 20, QuarantineRecord::REASON_RECALLED, 'Manufacturer recall', $pm);

    actAs($staff);
    $action = app(RequestDisposal::class);

    // Quantity above batch quarantine stock (25 > 20) is rejected
    expect(fn () => $action->execute($staff, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 25,
        'reason' => 'recalled',
        'method' => 'return_to_supplier',
        'supplier_name' => 'MedSupply Ethiopia',
    ]))->toThrow(ValidationException::class);

    // Valid quantity (12 units)
    $disposal = $action->execute($staff, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 12,
        'reason' => 'recalled',
        'method' => 'return_to_supplier',
        'supplier_name' => 'MedSupply Ethiopia',
    ]);

    expect($disposal->isReturnToSupplier())->toBeTrue()
        ->and($disposal->supplier_name)->toBe('MedSupply Ethiopia');

    reauth();
    actAs($pm);
    app(ApprovalService::class)->approve($disposal->approvalRequest, $pm, 'Return approved');

    // Quarantine stock is reduced specifically for this batch: 20 - 12 = 8
    expect($batch->fresh()->quarantineStockQuantity())->toBe(8);

    $movement = StockMovement::where('source_type', Disposal::class)->first();
    expect($movement->type)->toBe('supplier_return')
        ->and($movement->quantity)->toBe(-12);
});

test('system administrator cannot access disposal routes or actions (C-4)', function () {
    $sa = sa();
    actAs($sa);

    $this->get(route('inventory.disposals.index'))->assertForbidden();

    expect(fn () => app(RequestDisposal::class)->execute($sa, [
        'quarantine_record_id' => 1,
        'quantity' => 5,
        'reason' => 'expired',
        'method' => 'incineration',
    ]))->toThrow(AuthorizationException::class);
});

test('Livewire Index renders, filters, and submits disposal request', function () {
    $staff = ps();
    $pm = pm();
    $product = makeDisposalProduct();
    $batch = makeDisposalBatch($product);

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');
    $qr = app(QuarantineStock::class)->execute($batch, 15, QuarantineRecord::REASON_EXPIRED, 'Test', $pm);

    actAs($staff);

    Livewire::test(Index::class)
        ->assertOk()
        ->set('quarantine_record_id', $qr->id)
        ->set('quantity', 10)
        ->set('reason', 'expired')
        ->set('method', 'return_to_supplier')
        ->set('supplier_name', 'EPSS')
        ->call('submitDisposal')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Disposal::where('quarantine_record_id', $qr->id)->count())->toBe(1);
});

test('Livewire Show and Certificate views render properly', function () {
    $pm = pm();
    $product = makeDisposalProduct();
    $batch = makeDisposalBatch($product);

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');
    $qr = app(QuarantineStock::class)->execute($batch, 20, QuarantineRecord::REASON_EXPIRED, 'Test', $pm);

    actAs($pm);
    $disposal = app(RequestDisposal::class)->execute($pm, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 10,
        'reason' => 'expired',
        'method' => 'incineration',
    ]);

    reauth();
    $owner = owner();
    actAs($owner);
    app(ApprovalService::class)->approve($disposal->approvalRequest, $owner, 'Approved');

    $disposal->refresh();

    // Show view
    Livewire::test(Show::class, ['disposal' => $disposal])
        ->assertOk()
        ->assertSee($disposal->certificate_no)
        ->assertSee($product->brand);

    // Certificate view with signatures
    Livewire::test(Certificate::class, ['disposal' => $disposal])
        ->assertOk()
        ->assertSee($disposal->certificate_no)
        ->assertSee(__('disposal.sig_performer'))
        ->assertSee(__('disposal.sig_approver'));
});

test('Disposal lossReportQuery filters and aggregates records (FR-2.20)', function () {
    $pm = pm();
    $owner = owner();
    $product = makeDisposalProduct();
    $batch = makeDisposalBatch($product, ['unit_cost' => 500]);

    StockMovementService::post($batch, 50, 'receipt', $product, $pm, 'sellable');
    $qr = app(QuarantineStock::class)->execute($batch, 30, QuarantineRecord::REASON_EXPIRED, 'Test', $pm);

    actAs($pm);
    $disposal = app(RequestDisposal::class)->execute($pm, [
        'quarantine_record_id' => $qr->id,
        'quantity' => 15,
        'reason' => 'expired',
        'method' => 'incineration',
    ]);

    reauth();
    actAs($owner);
    app(ApprovalService::class)->approve($disposal->approvalRequest, $owner, 'Approved');

    $results = Disposal::lossReportQuery(
        startDate: now()->subDay()->format('Y-m-d'),
        endDate: now()->addDay()->format('Y-m-d'),
        method: 'incineration',
        reason: 'expired'
    )->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->lossValue())->toBe(15 * 500);
});
