<?php

use App\Actions\Finance\RecordExpense;
use App\Actions\Sales\RequestVoid;
use App\Approvals\Handlers\VoidSaleHandler;
use App\Livewire\Sales\Show as SalesShow;
use App\Livewire\Settings\ExpenseCategories as ExpenseCategoriesLivewire;
use App\Livewire\Settings\PaymentMethods as PaymentMethodsLivewire;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ReceiptPrintLog;
use App\Models\Sale;
use App\Models\SaleVoid;
use App\Models\Shift;
use App\Models\Till;
use App\Models\User;
use App\Reports\Definitions\Audit\AuditVoidsReport;
use App\Reports\Definitions\Sales\SalesByPaymentMethodReport;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-15/UC-28/UC-30 — Void record, receipt reprint log, payment method and expense category masters.
 *
 * @uc UC-15, UC-28, UC-30
 *
 * @fr FR-3.7, FR-6.2, FR-7.1
 *
 * @br BR-05, BR-14
 */
beforeEach(fn () => seedRoles());

// ─── Shared helpers ────────────────────────────────────────────────────────

function mvrProduct(): Product
{
    return Product::create([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'dosage_form' => 'capsule',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'capsule',
        'is_active' => true,
        'loose_allowed' => true,
    ]);
}

function mvrBatch(Product $product, int $qty = 100): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'MVR-'.Str::random(5),
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => 300,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $qty,
        'status' => 'sellable',
    ]);

    return $batch;
}

function mvrShift(User $user): Shift
{
    $till = Till::create(['name' => 'Till-MVR-'.Str::random(4), 'is_active' => true]);

    return Shift::create([
        'till_id' => $till->id,
        'user_id' => $user->id,
        'opened_at' => now(),
        'opening_float' => 10000,
        'status' => 'open',
    ]);
}

function mvrSale(User $staff, Shift $shift, Batch $batch, int $qty = 5, string $method = 'cash'): Sale
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

    $pm = PaymentMethod::where('code', $method)->first();

    $sale->payments()->create([
        'method' => $method,
        'amount' => $qty * 150,
        'payment_method_id' => $pm?->id,
    ]);

    return $sale;
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 1 — Void record (SaleVoid)
// ═══════════════════════════════════════════════════════════════════════════

it('VoidSaleHandler writes a SaleVoid row with correct fields', function () {
    $staff = ps();
    $manager = pm();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch);

    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Wrong item dispensed',
    );

    app(VoidSaleHandler::class)->onApproved($request, $manager);

    $void = SaleVoid::where('sale_id', $sale->id)->first();

    expect($void)->not->toBeNull()
        ->and($void->sale_id)->toBe($sale->id)
        ->and($void->reason)->toBe('Wrong item dispensed')
        ->and($void->requested_by)->toBe($staff->id)
        ->and($void->approved_by)->toBe($manager->id)
        ->and($void->approval_request_id)->toBe($request->id)
        ->and($void->voided_at)->not->toBeNull();
});

it('sale_voids is immutable — updates are blocked (BR-05)', function () {
    $staff = ps();
    $manager = pm();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch);

    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Immutability update test',
    );
    app(VoidSaleHandler::class)->onApproved($request, $manager);

    expect(fn () => SaleVoid::where('sale_id', $sale->id)->update(['reason' => 'Tampered'])
    )->toThrow(QueryException::class);
});

it('sale_voids is immutable — deletes are blocked (BR-05)', function () {
    $staff = ps();
    $manager = pm();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch);

    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Immutability delete test',
    );
    app(VoidSaleHandler::class)->onApproved($request, $manager);

    $sv = SaleVoid::where('sale_id', $sale->id)->firstOrFail();

    expect(fn () => $sv->delete())->toThrow(QueryException::class);
    expect(SaleVoid::where('sale_id', $sale->id)->exists())->toBeTrue();
});

it('sales show component displays void reason and approver after void', function () {
    $staff = ps();
    $manager = pm();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch);

    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Customer returned wrong product',
    );
    app(VoidSaleHandler::class)->onApproved($request, $manager);
    $sale->refresh();

    Livewire::actingAs($manager)
        ->test(SalesShow::class, ['sale' => $sale])
        ->assertSee('Customer returned wrong product')
        ->assertSee($manager->name);
});

it('AuditVoidsReport shows void reason from sale_voids', function () {
    $staff = ps();
    $manager = pm();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch);

    $request = app(RequestVoid::class)->execute(
        initiator: $staff,
        receiptNo: $sale->receipt_no,
        reason: 'Duplicate transaction detected',
    );
    app(VoidSaleHandler::class)->onApproved($request, $manager);

    $report = app(AuditVoidsReport::class);
    $result = $report->query([], $manager);

    $reasons = collect($result['rows'])->map(fn ($row) => $row[array_key_last($row)] ?? '')->flatten();
    expect($reasons->contains('Duplicate transaction detected'))->toBeTrue();
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 2 — Receipt Print Log
// ═══════════════════════════════════════════════════════════════════════════

it('first view of a receipt logs a non-reprint entry', function () {
    $staff = ps();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch);

    // No logs yet
    expect(ReceiptPrintLog::where('sale_id', $sale->id)->count())->toBe(0);

    $this->actingAs($staff)->get("/sales/{$sale->id}/receipt");

    expect(ReceiptPrintLog::where('sale_id', $sale->id)->count())->toBe(1);
    $log = ReceiptPrintLog::where('sale_id', $sale->id)->first();
    expect($log->is_reprint)->toBeFalse()
        ->and($log->reason)->toBeNull()
        ->and($log->user_id)->toBe($staff->id);
});

it('reprint request with reason logs a reprint entry', function () {
    $staff = ps();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch);

    $this->actingAs($staff)->get("/sales/{$sale->id}/receipt?reprint=1&reason=Customer+lost+original");

    $log = ReceiptPrintLog::where('sale_id', $sale->id)->where('is_reprint', true)->first();
    expect($log)->not->toBeNull()
        ->and($log->reason)->toBe('Customer lost original')
        ->and($log->user_id)->toBe($staff->id);
});

it('reprintCount() returns count of is_reprint=true log entries', function () {
    $staff = ps();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch);

    // 0 reprints initially
    expect($sale->reprintCount())->toBe(0);

    // Create 2 reprint logs
    ReceiptPrintLog::create([
        'sale_id' => $sale->id,
        'user_id' => $staff->id,
        'printed_at' => now(),
        'is_reprint' => true,
        'reason' => 'First copy',
    ]);
    ReceiptPrintLog::create([
        'sale_id' => $sale->id,
        'user_id' => $staff->id,
        'printed_at' => now(),
        'is_reprint' => true,
        'reason' => 'Second copy',
    ]);

    expect($sale->reprintCount())->toBe(2);
});

it('receipt view shows COPY label on reprint', function () {
    $staff = ps();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch);

    // Prime the sale so it has a reprint_count > 0
    $sale->update(['reprint_count' => 1]);

    $response = $this->actingAs($staff)->get("/sales/{$sale->id}/receipt?reprint=1&reason=Test+copy");
    $response->assertSee('COPY');
});

it('receipt_print_logs is immutable — updates are blocked', function () {
    $staff = ps();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch);

    ReceiptPrintLog::create([
        'sale_id' => $sale->id,
        'user_id' => $staff->id,
        'printed_at' => now(),
        'is_reprint' => false,
        'reason' => null,
    ]);

    expect(fn () => ReceiptPrintLog::where('sale_id', $sale->id)->update(['reason' => 'Tampered'])
    )->toThrow(QueryException::class);
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 3 — Payment Methods Master
// ═══════════════════════════════════════════════════════════════════════════

it('all 7 seeded payment methods exist and are active', function () {
    $codes = ['cash', 'mobile_money', 'telebirr', 'cbe_birr', 'card', 'bank_transfer', 'credit'];

    foreach ($codes as $code) {
        $pm = PaymentMethod::where('code', $code)->first();
        expect($pm)->not->toBeNull("Missing seeded payment method: {$code}")
            ->and($pm->is_active)->toBeTrue();
    }
});

it('cash is_cash=true; mobile_money requires_reference=true', function () {
    $cash = PaymentMethod::where('code', 'cash')->first();
    expect($cash->is_cash)->toBeTrue()
        ->and($cash->requires_reference)->toBeFalse();

    $mm = PaymentMethod::where('code', 'mobile_money')->first();
    expect($mm->is_cash)->toBeFalse()
        ->and($mm->requires_reference)->toBeTrue();
});

it('Owner can create a new payment method via Settings\PaymentMethods', function () {
    $ownerUser = owner();

    Livewire::actingAs($ownerUser)
        ->test(PaymentMethodsLivewire::class)
        ->call('openCreateModal')
        ->set('code', 'crypto')
        ->set('name', 'Crypto Wallet')
        ->set('is_cash', false)
        ->set('requires_reference', true)
        ->call('save');

    expect(PaymentMethod::where('code', 'crypto')->exists())->toBeTrue();
    expect(AuditLog::where('action', 'settings.payment_method.created')->exists())->toBeTrue();
});

it('PharmacyManager can edit a payment method via Settings\PaymentMethods', function () {
    $manager = pm();
    $pm = PaymentMethod::create([
        'code' => 'test_edit',
        'name' => 'Test Edit',
        'is_cash' => false,
        'is_active' => true,
        'requires_reference' => false,
    ]);

    Livewire::actingAs($manager)
        ->test(PaymentMethodsLivewire::class)
        ->call('edit', $pm->id)
        ->set('name', 'Test Edited Name')
        ->call('save');

    expect(PaymentMethod::find($pm->id)->name)->toBe('Test Edited Name');
    expect(AuditLog::where('action', 'settings.payment_method.updated')->exists())->toBeTrue();
});

it('Owner can toggle payment method active status', function () {
    $ownerUser = owner();
    $pm = PaymentMethod::where('code', 'card')->first();
    $original = $pm->is_active;

    Livewire::actingAs($ownerUser)
        ->test(PaymentMethodsLivewire::class)
        ->call('toggleActive', $pm->id);

    expect(PaymentMethod::find($pm->id)->is_active)->toBe(! $original);
    expect(AuditLog::where('action', 'settings.payment_method.status_changed')->exists())->toBeTrue();
});

it('PharmacyStaff cannot access Settings\PaymentMethods', function () {
    $staff = ps();

    Livewire::actingAs($staff)
        ->test(PaymentMethodsLivewire::class)
        ->assertForbidden();
});

it('completed sale payment has payment_method_id populated', function () {
    $staff = ps();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct());
    $sale = mvrSale($staff, $shift, $batch, 5, 'cash');

    $payment = $sale->payments()->first();
    expect($payment->payment_method_id)->not->toBeNull();

    $method = PaymentMethod::find($payment->payment_method_id);
    expect($method->code)->toBe('cash');
});

it('SalesByPaymentMethodReport aggregates by payment method correctly', function () {
    $staff = ps();
    $manager = pm();
    $shift = mvrShift($staff);
    $batch = mvrBatch(mvrProduct(), 200);

    mvrSale($staff, $shift, $batch, 5, 'cash');      // 750
    mvrSale($staff, $shift, $batch, 3, 'cash');      // 450
    mvrSale($staff, $shift, $batch, 2, 'telebirr');  // 300

    $report = app(SalesByPaymentMethodReport::class);
    $result = $report->query(
        ['date_from' => now()->toDateString(), 'date_to' => now()->toDateString()],
        $manager
    );

    // Extract method labels from rows
    $labels = collect($result['rows'])->pluck(0)->map(fn ($s) => strtolower($s));
    expect($labels->contains(fn ($l) => str_contains($l, 'cash')))->toBeTrue();
    expect($labels->contains(fn ($l) => str_contains($l, 'telebirr')))->toBeTrue();

    // Cash row total received = 750 + 450 = 1200
    $cashRow = collect($result['rows'])->first(fn ($r) => str_contains(strtolower($r[0]), 'cash'));
    expect($cashRow)->not->toBeNull();
    // count = 2 cash payments
    expect((int) $cashRow[1])->toBe(2);
});

// ═══════════════════════════════════════════════════════════════════════════
// SECTION 4 — Expense Categories Master
// ═══════════════════════════════════════════════════════════════════════════

it('all 7 seeded expense categories exist and are active', function () {
    $codes = ['rent', 'salaries', 'utilities', 'transport', 'maintenance', 'supplies', 'other'];

    foreach ($codes as $code) {
        $cat = ExpenseCategory::where('code', $code)->first();
        expect($cat)->not->toBeNull("Missing seeded expense category: {$code}")
            ->and($cat->is_active)->toBeTrue();
    }
});

it('Owner can create a new expense category via Settings\ExpenseCategories', function () {
    $ownerUser = owner();

    Livewire::actingAs($ownerUser)
        ->test(ExpenseCategoriesLivewire::class)
        ->call('openCreateModal')
        ->set('code', 'marketing')
        ->set('name', 'Marketing & Advertising')
        ->call('save');

    expect(ExpenseCategory::where('code', 'marketing')->exists())->toBeTrue();
    expect(AuditLog::where('action', 'settings.expense_category.created')->exists())->toBeTrue();
});

it('PharmacyManager can edit an expense category via Settings\ExpenseCategories', function () {
    $manager = pm();
    $cat = ExpenseCategory::create([
        'code' => 'test_cat',
        'name' => 'Test Category',
        'is_active' => true,
    ]);

    Livewire::actingAs($manager)
        ->test(ExpenseCategoriesLivewire::class)
        ->call('edit', $cat->id)
        ->set('name', 'Updated Category Name')
        ->call('save');

    expect(ExpenseCategory::find($cat->id)->name)->toBe('Updated Category Name');
    expect(AuditLog::where('action', 'settings.expense_category.updated')->exists())->toBeTrue();
});

it('Owner can toggle expense category active status', function () {
    $ownerUser = owner();
    $cat = ExpenseCategory::where('code', 'supplies')->first();
    $original = $cat->is_active;

    Livewire::actingAs($ownerUser)
        ->test(ExpenseCategoriesLivewire::class)
        ->call('toggleActive', $cat->id);

    expect(ExpenseCategory::find($cat->id)->is_active)->toBe(! $original);
    expect(AuditLog::where('action', 'settings.expense_category.status_changed')->exists())->toBeTrue();
});

it('PharmacyStaff cannot access Settings\ExpenseCategories', function () {
    $staff = ps();

    Livewire::actingAs($staff)
        ->test(ExpenseCategoriesLivewire::class)
        ->assertForbidden();
});

it('RecordExpense populates expense_category_id by code', function () {
    $ownerUser = owner();
    $action = app(RecordExpense::class);

    $expense = $action->execute($ownerUser, [
        'category' => 'rent',
        'amount' => 50000,
        'date' => now()->toDateString(),
        'description' => 'Monthly store rent',
    ]);

    expect($expense->expense_category_id)->not->toBeNull();
    $cat = ExpenseCategory::find($expense->expense_category_id);
    expect($cat->code)->toBe('rent');
});

it('RecordExpense populates expense_category_id by numeric ID', function () {
    $ownerUser = owner();
    $action = app(RecordExpense::class);
    $cat = ExpenseCategory::where('code', 'utilities')->first();

    $expense = $action->execute($ownerUser, [
        'category' => (string) $cat->id,
        'amount' => 15000,
        'date' => now()->toDateString(),
        'description' => 'Electric bill',
    ]);

    expect($expense->expense_category_id)->toBe($cat->id);
});

it('duplicate payment_method code is rejected', function () {
    PaymentMethod::create([
        'code' => 'unique_pm',
        'name' => 'First',
        'is_cash' => false,
        'is_active' => true,
        'requires_reference' => false,
    ]);

    $manager = pm();

    Livewire::actingAs($manager)
        ->test(PaymentMethodsLivewire::class)
        ->call('openCreateModal')
        ->set('code', 'unique_pm')
        ->set('name', 'Duplicate')
        ->call('save')
        ->assertHasErrors(['code']);
});

it('duplicate expense_category code is rejected', function () {
    ExpenseCategory::create([
        'code' => 'unique_cat',
        'name' => 'Unique Cat',
        'is_active' => true,
    ]);

    $manager = pm();

    Livewire::actingAs($manager)
        ->test(ExpenseCategoriesLivewire::class)
        ->call('openCreateModal')
        ->set('code', 'unique_cat')
        ->set('name', 'Duplicate Cat')
        ->call('save')
        ->assertHasErrors(['code']);
});
