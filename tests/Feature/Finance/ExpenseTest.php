<?php

use App\Actions\Finance\RecordExpense;
use App\Enums\Role as RoleEnum;
use App\Exceptions\ApprovalException;
use App\Livewire\Finance\Expenses\Index;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Services\ApprovalService;
use App\Services\SettingsRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('finance.expense_approval_limit', 50000); // 500.00 ETB
});

test('PM or Owner can record expense with all fields when within limit (FR-6.2)', function () {
    $pm = pm();
    $action = app(RecordExpense::class);

    $expense = $action->execute($pm, [
        'category' => Expense::CATEGORY_UTILITIES,
        'amount' => 15000, // 150.00 ETB (within 500.00 ETB limit)
        'date' => now()->toDateString(),
        'description' => 'Monthly electricity bill for October',
        'proof_path' => 'expenses/proofs/electric_bill.pdf',
    ]);

    expect($expense)->not->toBeNull()
        ->and($expense->category)->toBe(Expense::CATEGORY_UTILITIES)
        ->and($expense->amount)->toBe(15000)
        ->and($expense->status)->toBe(Expense::STATUS_POSTED)
        ->and($expense->created_by)->toBe($pm->id)
        ->and($expense->approved_by)->toBe($pm->id)
        ->and($expense->approval_request_id)->toBeNull()
        ->and($expense->proof_path)->toBe('expenses/proofs/electric_bill.pdf');

    expect(AuditLog::where('action', 'expense.recorded')->where('auditable_id', (string) $expense->id)->exists())->toBeTrue();
});

test('missing category or amount or description is refused (E1)', function () {
    $pm = pm();
    $action = app(RecordExpense::class);

    // Missing category
    expect(fn () => $action->execute($pm, [
        'amount' => 5000,
        'description' => 'Valid description',
    ]))->toThrow(ValidationException::class);

    // Missing or invalid amount (zero or negative)
    expect(fn () => $action->execute($pm, [
        'category' => Expense::CATEGORY_TRANSPORT,
        'amount' => 0,
        'description' => 'Valid description',
    ]))->toThrow(ValidationException::class);

    // Missing description
    expect(fn () => $action->execute($pm, [
        'category' => Expense::CATEGORY_TRANSPORT,
        'amount' => 5000,
        'description' => '',
    ]))->toThrow(ValidationException::class);
});

test('above-limit PM expense goes to Owner approval and PM cannot approve their own (FR-6.2, OQ-06)', function () {
    $pm = pm();
    $ownerUser = owner();
    $action = app(RecordExpense::class);

    // Limit is 500.00 ETB (50000 santim). PM submits 800.00 ETB (80000 santim)
    $expense = $action->execute($pm, [
        'category' => Expense::CATEGORY_RENT,
        'amount' => 80000,
        'description' => 'Store monthly rent payment',
    ]);

    expect($expense->status)->toBe(Expense::STATUS_PENDING_APPROVAL)
        ->and($expense->approved_by)->toBeNull()
        ->and($expense->approval_request_id)->not->toBeNull();

    $approvalRequest = $expense->approvalRequest;
    expect($approvalRequest->type)->toBe('finance.expense')
        ->and($approvalRequest->eligible_roles)->toBe([RoleEnum::Owner->value])
        ->and($approvalRequest->initiator_id)->toBe($pm->id);

    // PM cannot approve their own expense
    actAs($pm);
    reauth('approval.decide');
    expect(fn () => app(ApprovalService::class)->approve($approvalRequest, $pm))
        ->toThrow(ApprovalException::class);

    // Owner approves the expense
    actAs($ownerUser);
    reauth('approval.decide');
    app(ApprovalService::class)->approve($approvalRequest, $ownerUser, 'Approved store rent payment');

    $expense->refresh();
    expect($expense->status)->toBe(Expense::STATUS_POSTED)
        ->and($expense->approved_by)->toBe($ownerUser->id);

    expect(AuditLog::where('action', 'expense.approved')->where('auditable_id', (string) $expense->id)->exists())->toBeTrue();
});

test('when expense limit is Not set, default policy requires Owner approval for PM entries', function () {
    app(SettingsRepository::class)->put('finance.expense_approval_limit', null);

    $pm = pm();
    $action = app(RecordExpense::class);

    $expense = $action->execute($pm, [
        'category' => Expense::CATEGORY_OTHER,
        'amount' => 2000, // Small amount: 20 ETB
        'description' => 'Cleaning supplies',
    ]);

    expect($expense->status)->toBe(Expense::STATUS_PENDING_APPROVAL)
        ->and($expense->approval_request_id)->not->toBeNull();
});

test('Owner entries are posted immediately even above limit', function () {
    $ownerUser = owner();
    $action = app(RecordExpense::class);

    $expense = $action->execute($ownerUser, [
        'category' => Expense::CATEGORY_SALARIES,
        'amount' => 500000, // 5,000.00 ETB, well above 500.00 ETB limit
        'description' => 'Monthly staff wages disbursement',
    ]);

    expect($expense->status)->toBe(Expense::STATUS_POSTED)
        ->and($expense->approved_by)->toBe($ownerUser->id)
        ->and($expense->approval_request_id)->toBeNull();
});

test('rejection of expense approval leaves status rejected and not posted', function () {
    $pm = pm();
    $ownerUser = owner();
    $action = app(RecordExpense::class);

    $expense = $action->execute($pm, [
        'category' => Expense::CATEGORY_OTHER,
        'amount' => 100000,
        'description' => 'Luxury office chair',
    ]);

    $approvalRequest = $expense->approvalRequest;

    actAs($ownerUser);
    reauth('approval.decide');
    app(ApprovalService::class)->reject($approvalRequest, $ownerUser, 'Not an approved expenditure');

    $expense->refresh();
    expect($expense->status)->toBe(Expense::STATUS_REJECTED)
        ->and($expense->approved_by)->toBe($ownerUser->id);

    expect(AuditLog::where('action', 'expense.rejected')->where('auditable_id', (string) $expense->id)->exists())->toBeTrue();
});

test('expenses are never deleted and corrections are made via reversal entry (STEP 3)', function () {
    $pm = pm();
    $action = app(RecordExpense::class);

    $expense = $action->execute($pm, [
        'category' => Expense::CATEGORY_TRANSPORT,
        'amount' => 5000,
        'description' => 'Courier fee for delivery',
    ]);

    // Model deleting hook prevents deletion
    expect(fn () => $expense->delete())->toThrow(RuntimeException::class);

    // Database level trigger prevents deletion
    expect(fn () => DB::table('expenses')->where('id', $expense->id)->delete())
        ->toThrow(QueryException::class);

    // Reversal entry
    $reversal = $action->reverse($pm, $expense, 'Incorrect courier charge recorded twice');

    expect($reversal)->not->toBeNull()
        ->and($reversal->category)->toBe(Expense::CATEGORY_TRANSPORT)
        ->and($reversal->amount)->toBe(-5000)
        ->and($reversal->reversal_of_id)->toBe($expense->id)
        ->and($reversal->reversal_reason)->toBe('Incorrect courier charge recorded twice')
        ->and($reversal->status)->toBe(Expense::STATUS_POSTED)
        ->and($reversal->isReversal())->toBeTrue();

    // Cannot reverse a reversal
    expect(fn () => $action->reverse($pm, $reversal, 'Cannot reverse again'))->toThrow(RuntimeException::class);

    // Cannot reverse an expense that already has a reversal
    expect(fn () => $action->reverse($pm, $expense, 'Cannot reverse twice'))->toThrow(RuntimeException::class);

    expect(AuditLog::where('action', 'expense.reversed')->where('auditable_id', (string) $expense->id)->exists())->toBeTrue();
});

test('pharmacy staff and SA are denied access (AC-24, C-4)', function () {
    $staff = ps();
    $saUser = sa();
    $action = app(RecordExpense::class);

    // Staff denied via action
    expect(fn () => $action->execute($staff, [
        'category' => Expense::CATEGORY_RENT,
        'amount' => 5000,
        'description' => 'Staff attempted expense',
    ]))->toThrow(AuthorizationException::class);

    // SA denied via action
    expect(fn () => $action->execute($saUser, [
        'category' => Expense::CATEGORY_RENT,
        'amount' => 5000,
        'description' => 'SA attempted expense',
    ]))->toThrow(AuthorizationException::class);

    // Staff denied via HTTP
    actAs($staff);
    $this->get(route('finance.expenses.index'))->assertStatus(403);

    // SA denied via HTTP
    actAs($saUser);
    $this->get(route('finance.expenses.index'))->assertStatus(403);
});

test('entries appear in the UC-29 summary queries (posted and period scopes)', function () {
    $ownerUser = owner();
    $action = app(RecordExpense::class);

    // Expense 1: 300.00 ETB posted
    $exp1 = $action->execute($ownerUser, [
        'category' => Expense::CATEGORY_RENT,
        'amount' => 30000,
        'date' => now()->toDateString(),
        'description' => 'Rent Oct',
    ]);

    // Expense 2: 100.00 ETB posted
    $exp2 = $action->execute($ownerUser, [
        'category' => Expense::CATEGORY_UTILITIES,
        'amount' => 10000,
        'date' => now()->toDateString(),
        'description' => 'Water bill',
    ]);

    // Expense 2 reversed (-100.00 ETB)
    $action->reverse($ownerUser, $exp2, 'Wrong bill amount');

    // Expense 3: Pending approval (not posted)
    $pm = pm();
    $action->execute($pm, [
        'category' => Expense::CATEGORY_SALARIES,
        'amount' => 80000, // Above 500.00 limit -> pending
        'date' => now()->toDateString(),
        'description' => 'Advance salary',
    ]);

    // UC-29 posted expense summary query:
    // Only posted entries are included. Reversal nets out Expense 2.
    // 30000 + 10000 - 10000 = 30000 santim (300.00 ETB)
    $totalPostedExpenses = Expense::posted()->sum('amount');
    expect($totalPostedExpenses)->toBe(30000);

    // Scope for period
    $todayExpenses = Expense::posted()
        ->forPeriod(now()->toDateString(), now()->toDateString())
        ->sum('amount');
    expect($todayExpenses)->toBe(30000);

    // By category
    $rentTotal = Expense::posted()->where('category', Expense::CATEGORY_RENT)->sum('amount');
    expect($rentTotal)->toBe(30000);

    $utilitiesTotal = Expense::posted()->where('category', Expense::CATEGORY_UTILITIES)->sum('amount');
    expect($utilitiesTotal)->toBe(0); // 10000 - 10000 = 0
});

test('Livewire Index component renders, records expense, and executes reversal', function () {
    $pm = pm();
    actAs($pm);

    Livewire::test(Index::class)
        ->assertStatus(200)
        ->assertSee(__('expense.title'))
        ->set('category', Expense::CATEGORY_TRANSPORT)
        ->set('amount', '45.50')
        ->set('date', now()->toDateString())
        ->set('description', 'Local messenger delivery service')
        ->call('saveExpense')
        ->assertHasNoErrors();

    $expense = Expense::where('description', 'Local messenger delivery service')->first();
    expect($expense)->not->toBeNull()
        ->and($expense->amount)->toBe(4550)
        ->and($expense->status)->toBe(Expense::STATUS_POSTED);

    // Reverse via Livewire
    Livewire::test(Index::class)
        ->call('openReverseModal', $expense->id)
        ->set('reversalReason', 'Wrong service charged')
        ->call('confirmReverse')
        ->assertHasNoErrors();

    $reversal = Expense::where('reversal_of_id', $expense->id)->first();
    expect($reversal)->not->toBeNull()
        ->and($reversal->amount)->toBe(-4550);
});
