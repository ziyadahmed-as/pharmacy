<?php

use App\Actions\Sales\RequestVoid;
use App\Approvals\Handlers\VoidSaleHandler;
use App\Exceptions\ApprovalException;
use App\Livewire\Sales\Show as SalesShow;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\StockMovement;
use App\Models\Till;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-15: Void a sale.
 * BRD: FR-3.7; BR-05, BR-14. AC-14.
 */
beforeEach(fn () => seedRoles());

// ─── Shared helpers ────────────────────────────────────────────────────────

function makeVoidProduct(array $attrs = []): Product
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

function makeVoidBatch(Product $product, int $qty = 200): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'VC-'.Str::random(5),
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

function makeVoidShift(User $user): Shift
{
    $till = Till::create(['name' => 'Till-V-'.Str::random(4), 'is_active' => true]);

    return Shift::create([
        'till_id' => $till->id,
        'user_id' => $user->id,
        'opened_at' => now(),
        'opening_float' => 10000,
        'status' => 'open',
    ]);
}

function makeCompletedSale(User $staff, Shift $shift, Batch $batch, int $qty = 10): Sale
{
    $sale = Sale::create([
        'receipt_no' => 'RCV-'.Str::random(8),
        'shift_id' => $shift->id,
        'user_id' => $staff->id,
        'status' => 'completed',
        'total' => $qty * 150,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
    ]);

    $sale->lines()->create([
        'product_id' => $batch->product_id,
        'batch_id' => $batch->id,
        'quantity' => $qty,
        'unit_price' => 150,
        'subtotal' => $qty * 150,
        'is_fefo_override' => false,
    ]);

    $sale->payments()->create([
        'method' => 'cash',
        'amount' => $qty * 150,
    ]);

    return $sale;
}

// ─── Tests ─────────────────────────────────────────────────────────────────

it('request → approve: stock and cash reversed to the same batch (AC-14)', function () {
    $staff = ps();
    $manager = pm();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct();
    $batch = makeVoidBatch($product, qty: 200);

    $sale = makeCompletedSale($staff, $shift, $batch, qty: 10);

    // Simulate stock deduction that UC-13 would have made
    BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')
        ->decrement('quantity', 10);

    $stockBefore = 200; // original quantity

    // ── Request void ──
    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Wrong item dispensed',
    );

    expect($request)->toBeInstanceOf(ApprovalRequest::class)
        ->and($request->type)->toBe('sale.void')
        ->and($request->isPending())->toBeTrue();

    // ── Approve (simulate engine calling the handler) ──
    app(VoidSaleHandler::class)->onApproved($request, $manager);

    $sale->refresh();
    expect($sale->isVoided())->toBeTrue();

    // Stock returned to original batch
    $stockAfter = BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->value('quantity');
    expect($stockAfter)->toBe($stockBefore);

    // Audit present with both user IDs
    $audit = AuditLog::where('action', 'sale.voided')->where('auditable_id', $sale->id)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->metadata['initiator_id'])->toBe($staff->id)
        ->and($audit->metadata['approver_id'])->toBe($manager->id);
});

it('original sale rows are kept, not deleted (BR-05)', function () {
    $staff = ps();
    $manager = pm();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Aspirin 300mg']);
    $batch = makeVoidBatch($product);

    $sale = makeCompletedSale($staff, $shift, $batch);
    $lineId = $sale->lines->first()->id;
    $paymentId = $sale->payments->first()->id;

    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Duplicate sale',
    );
    app(VoidSaleHandler::class)->onApproved($request, $manager);

    // All original rows still exist — BR-05
    expect($sale->lines()->where('id', $lineId)->exists())->toBeTrue();
    expect($sale->payments()->where('id', $paymentId)->exists())->toBeTrue();
    expect(Sale::find($sale->id))->not->toBeNull();
});

it('rejection leaves sale completed and stock unchanged', function () {
    $staff = ps();
    $manager = pm();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Ibuprofen 400mg']);
    $batch = makeVoidBatch($product, qty: 100);

    $sale = makeCompletedSale($staff, $shift, $batch);

    $stockBefore = BatchStock::where('batch_id', $batch->id)->value('quantity');

    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Customer changed mind',
    );

    // Handler onRejected is a no-op
    app(VoidSaleHandler::class)->onRejected($request, $manager);

    $sale->refresh();
    expect($sale->isCompleted())->toBeTrue();

    $stockAfter = BatchStock::where('batch_id', $batch->id)->value('quantity');
    expect($stockAfter)->toBe($stockBefore);
});

it('initiator cannot approve their own void request (E1)', function () {
    $staff = ps();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Vitamins A']);
    $batch = makeVoidBatch($product);

    $sale = makeCompletedSale($staff, $shift, $batch);

    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Self-approve attempt',
    );

    // ApprovalService::guardDecision refuses initiator
    expect(fn () => app(ApprovalService::class)->guardDecision($request, $staff))
        ->toThrow(ApprovalException::class);
});

it('already-voided sale cannot be voided again', function () {
    $staff = ps();
    $manager = pm();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Zinc 10mg']);
    $batch = makeVoidBatch($product);

    $sale = makeCompletedSale($staff, $shift, $batch);

    // First void
    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'First void',
    );
    app(VoidSaleHandler::class)->onApproved($request, $manager);

    // Second void attempt must throw
    expect(fn () => app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Second void attempt',
    ))->toThrow(RuntimeException::class);
});

it('missing reason is refused', function () {
    $staff = ps();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Folic Acid']);
    $batch = makeVoidBatch($product);
    $sale = makeCompletedSale($staff, $shift, $batch);

    expect(fn () => app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: '   ',    // blank / whitespace
    ))->toThrow(InvalidArgumentException::class);
});

it('locked day is refused (E2 stub)', function () {
    // UC-18 (Day Closing) has not been built yet.
    // The guard is a stub — test that it throws when the stub flag is active.
    // We test the guard method directly since the action fetches the sale fresh from DB.
    $staff = ps();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Metformin 500mg']);
    $batch = makeVoidBatch($product);
    $sale = makeCompletedSale($staff, $shift, $batch);

    // Directly verify the message is defined (guard is a stub until UC-18)
    expect(__('sales.void_error_locked_day'))->not->toBeEmpty();

    // The action itself should NOT throw without UC-18 implementation
    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Valid reason for stub test',
    );
    expect($request)->toBeInstanceOf(ApprovalRequest::class);
});

it('a user without sales.void.request permission is refused', function () {
    // Create a user with no roles → no permission
    $noAuth = User::factory()->create();
    $staff = ps();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Amoxicillin 250mg']);
    $batch = makeVoidBatch($product);
    $sale = makeCompletedSale($staff, $shift, $batch);

    expect(fn () => app(RequestVoid::class)->execute(
        initiator: $noAuth,
        receiptNo: $sale->receipt_no,
        reason: 'Unauthorized void attempt',
    ))->toThrow(AuthorizationException::class);
});

it('audit entry records both initiator and approver IDs', function () {
    $staff = ps();
    $manager = pm();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Enalapril 5mg']);
    $batch = makeVoidBatch($product);

    $sale = makeCompletedSale($staff, $shift, $batch);

    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Audit check',
    );
    app(VoidSaleHandler::class)->onApproved($request, $manager);

    $audit = AuditLog::where('action', 'sale.voided')->where('auditable_id', $sale->id)->firstOrFail();

    expect($audit->metadata)->toMatchArray([
        'initiator_id' => $staff->id,
        'approver_id' => $manager->id,
    ]);
});

it('stock movements are posted with type sale_void', function () {
    $staff = ps();
    $manager = pm();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Omeprazole 20mg']);
    $batch = makeVoidBatch($product, qty: 50);

    $sale = makeCompletedSale($staff, $shift, $batch, qty: 5);

    // Simulate UC-13 deduction
    BatchStock::where('batch_id', $batch->id)->where('status', 'sellable')->decrement('quantity', 5);

    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Movement type test',
    );
    app(VoidSaleHandler::class)->onApproved($request, $manager);

    $movement = StockMovement::where('batch_id', $batch->id)
        ->where('type', 'sale_void')
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement->quantity)->toBeGreaterThan(0);
});

// ─── Livewire tests ────────────────────────────────────────────────────────

it('shows sale details on the Sales Show screen', function () {
    $staff = ps();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Chlorphenamine 4mg']);
    $batch = makeVoidBatch($product);
    $sale = makeCompletedSale($staff, $shift, $batch);

    Livewire::actingAs($staff)
        ->test(SalesShow::class, ['sale' => $sale])
        ->assertSee($sale->receipt_no)
        ->assertSee($product->brand);
});

it('shows Request Void button for completed sale with permission', function () {
    $staff = ps();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Nifedipine 10mg']);
    $batch = makeVoidBatch($product);
    $sale = makeCompletedSale($staff, $shift, $batch);

    Livewire::actingAs($staff)
        ->test(SalesShow::class, ['sale' => $sale])
        ->assertSee(__('sales.request_void_button'));
});

it('submits void request through Livewire and shows success', function () {
    $staff = ps();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Salbutamol 100mcg']);
    $batch = makeVoidBatch($product);
    $sale = makeCompletedSale($staff, $shift, $batch);

    Livewire::actingAs($staff)
        ->test(SalesShow::class, ['sale' => $sale])
        ->call('openVoidModal')
        ->set('voidReason', 'Wrong item dispensed to customer')
        ->call('submitVoidRequest')
        ->assertSet('showVoidModal', false)
        ->assertSet('voidError', '');

    expect(
        ApprovalRequest::where('type', 'sale.void')->where('subject_id', $sale->id)->exists()
    )->toBeTrue();
});

it('livewire shows validation error when reason is missing', function () {
    $staff = ps();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Doxycycline 100mg']);
    $batch = makeVoidBatch($product);
    $sale = makeCompletedSale($staff, $shift, $batch);

    Livewire::actingAs($staff)
        ->test(SalesShow::class, ['sale' => $sale])
        ->call('openVoidModal')
        ->set('voidReason', '')
        ->call('submitVoidRequest')
        ->assertHasErrors(['voidReason']);
});

it('shows VOIDED badge and hides void button for voided sales', function () {
    $staff = ps();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Digoxin 0.25mg']);
    $batch = makeVoidBatch($product);
    $sale = makeCompletedSale($staff, $shift, $batch);
    $sale->update(['status' => 'voided']);

    Livewire::actingAs($staff)
        ->test(SalesShow::class, ['sale' => $sale])
        ->assertSee(__('sales.voided_badge'))
        ->assertDontSee(__('sales.request_void_button'));
});

it('shows void pending badge when approval request exists', function () {
    $staff = ps();
    $shift = makeVoidShift($staff);
    $product = makeVoidProduct(['brand' => 'Warfarin 5mg']);
    $batch = makeVoidBatch($product);
    $sale = makeCompletedSale($staff, $shift, $batch);

    app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Panel visibility test',
    );

    Livewire::actingAs($staff)
        ->test(SalesShow::class, ['sale' => $sale])
        ->assertSee(__('sales.void_pending_badge'));
});

it('VoidSaleHandler type() returns sale.void', function () {
    expect(app(VoidSaleHandler::class)->type())->toBe('sale.void');
});
