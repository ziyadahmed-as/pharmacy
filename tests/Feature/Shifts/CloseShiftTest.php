<?php

use App\Actions\Shifts\CloseShift;
use App\Actions\Shifts\OpenShift;
use App\Actions\Shifts\RecordCashMovement;
use App\Livewire\Shifts\Close as ShiftsClose;
use App\Models\AuditLog;
use App\Models\HeldSale;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\ShiftCashMovement;
use App\Models\Till;
use App\Models\User;
use App\Services\ShiftExpectedCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-17: Close shift.
 * BRD: FR-3.9. AC-24.
 */
beforeEach(fn () => seedRoles());

function makeCloseShift(User $user, int $float = 10000): Shift
{
    $till = Till::create(['name' => 'Till-C-'.Str::random(4), 'is_active' => true]);

    return app(OpenShift::class)->execute($user, $till, $float);
}

function addCompletedSale(Shift $shift, array $tenders): Sale
{
    $total = array_sum(array_column($tenders, 'amount'));

    $sale = Sale::create([
        'receipt_no' => 'RCS-'.Str::random(8),
        'shift_id' => $shift->id,
        'user_id' => $shift->user_id,
        'status' => 'completed',
        'total' => $total,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
    ]);

    foreach ($tenders as $tender) {
        $sale->payments()->create([
            'method' => $tender['method'],
            'amount' => $tender['amount'],
        ]);
    }

    return $sale;
}

function addVoidedSale(Shift $shift, array $tenders): Sale
{
    $total = array_sum(array_column($tenders, 'amount'));

    $sale = Sale::create([
        'receipt_no' => 'RCV-'.Str::random(8),
        'shift_id' => $shift->id,
        'user_id' => $shift->user_id,
        'status' => 'voided',
        'total' => $total,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
    ]);

    foreach ($tenders as $tender) {
        $sale->payments()->create([
            'method' => $tender['method'],
            'amount' => $tender['amount'],
        ]);
    }

    return $sale;
}

it('expected totals match sales, cash movements and exclude voided sales', function () {
    $staff = ps();
    $shift = makeCloseShift($staff, float: 10000); // 100.00 ETB float

    // 1. Completed sale: 50.00 ETB cash, 30.00 ETB telebirr
    addCompletedSale($shift, [
        ['method' => 'cash', 'amount' => 5000],
        ['method' => 'telebirr', 'amount' => 3000],
    ]);

    // 2. Voided sale: 40.00 ETB cash (must be excluded from expected totals)
    addVoidedSale($shift, [
        ['method' => 'cash', 'amount' => 4000],
    ]);

    // 3. Cash in: 20.00 ETB
    app(RecordCashMovement::class)->execute(
        user: $staff,
        shift: $shift,
        type: ShiftCashMovement::TYPE_CASH_IN,
        amountSantim: 2000,
        reason: 'Added petty cash'
    );

    // 4. Cash out: 15.00 ETB
    app(RecordCashMovement::class)->execute(
        user: $staff,
        shift: $shift,
        type: ShiftCashMovement::TYPE_CASH_OUT,
        amountSantim: 1500,
        reason: 'Bank drop'
    );

    // Expected cash calculation:
    // 10000 (float) + 5000 (sale) + 2000 (in) - 1500 (out) = 15500 santim (155.00 ETB)
    // Expected telebirr: 3000 santim (30.00 ETB)
    $expected = ShiftExpectedCalculator::calculate($shift);

    expect($expected['cash'])->toBe(15500)
        ->and($expected['telebirr'])->toBe(3000)
        ->and(isset($expected['cbe_birr']))->toBeFalse();
});

it('closes shift cleanly when counted matches expected with zero variance', function () {
    $staff = ps();
    $shift = makeCloseShift($staff, float: 10000);

    addCompletedSale($shift, [
        ['method' => 'cash', 'amount' => 5000],
    ]);

    // Expected: 15000 santim cash
    $closedShift = app(CloseShift::class)->execute(
        user: $staff,
        shift: $shift,
        countedCashPerMethod: ['cash' => 15000]
    );

    expect($closedShift->isClosed())->toBeTrue()
        ->and($closedShift->variance)->toBe(0)
        ->and($closedShift->variance_reason)->toBeNull()
        ->and($closedShift->closed_at)->not->toBeNull()
        ->and($closedShift->counted_cash_per_payment_method['cash'])->toBe(15000);

    // Audit record present
    $audit = AuditLog::where('action', 'shift.closed')->where('auditable_id', $shift->id)->first();
    expect($audit)->not->toBeNull();
});

it('requires variance reason when counted cash does not match expected', function () {
    $staff = ps();
    $shift = makeCloseShift($staff, float: 10000);

    // Expected is 10000; counted is 9000 (shortage of 1000 santim)
    expect(fn () => app(CloseShift::class)->execute(
        user: $staff,
        shift: $shift,
        countedCashPerMethod: ['cash' => 9000],
        varianceReason: '' // empty reason must throw
    ))->toThrow(InvalidArgumentException::class);

    // With reason, closes successfully
    $closedShift = app(CloseShift::class)->execute(
        user: $staff,
        shift: $shift,
        countedCashPerMethod: ['cash' => 9000],
        varianceReason: 'Customer shortage / counterfeit note confiscated'
    );

    expect($closedShift->isClosed())->toBeTrue()
        ->and($closedShift->variance)->toBe(-1000)
        ->and($closedShift->variance_reason)->toBe('Customer shortage / counterfeit note confiscated');
});

it('refuses closing a shift twice (E1)', function () {
    $staff = ps();
    $shift = makeCloseShift($staff, float: 10000);

    app(CloseShift::class)->execute($staff, $shift, ['cash' => 10000]);

    expect(fn () => app(CloseShift::class)->execute(
        $staff,
        $shift,
        ['cash' => 10000]
    ))->toThrow(RuntimeException::class);
});

it('enforces that closed shifts are immutable', function () {
    $staff = ps();
    $shift = makeCloseShift($staff, float: 10000);

    app(CloseShift::class)->execute($staff, $shift, ['cash' => 10000]);

    $shift->refresh();

    // Updating a closed shift must throw
    expect(fn () => $shift->update(['variance' => 500]))
        ->toThrow(RuntimeException::class);
});

it('staff cannot see or close another users shift', function () {
    $staff1 = ps();
    $staff2 = ps();
    $shift1 = makeCloseShift($staff1, float: 10000);

    // Staff 2 trying to close Staff 1 shift is refused
    expect(fn () => app(CloseShift::class)->execute(
        $staff2,
        $shift1,
        ['cash' => 10000]
    ))->toThrow(AuthorizationException::class);

    // Staff 2 cannot record cash movements on Staff 1 shift
    expect(fn () => app(RecordCashMovement::class)->execute(
        $staff2,
        $shift1,
        'cash_in',
        5000,
        'Unauthorized move'
    ))->toThrow(AuthorizationException::class);
});

it('manager can close staff shift', function () {
    $staff = ps();
    $manager = pm();
    $shift = makeCloseShift($staff, float: 10000);

    $closed = app(CloseShift::class)->execute(
        $manager,
        $shift,
        ['cash' => 10000]
    );

    expect($closed->isClosed())->toBeTrue();
});

it('warns user when held sales exist on shift', function () {
    $staff = ps();
    $shift = makeCloseShift($staff, float: 10000);

    HeldSale::create([
        'user_id' => $staff->id,
        'shift_id' => $shift->id,
        'cart' => [['brand' => 'Test Item', 'quantity' => 1]],
        'total' => 5000,
        'held_at' => now(),
        'status' => 'held',
    ]);

    Livewire::actingAs($staff)
        ->test(ShiftsClose::class)
        ->assertSee(__('shifts.held_sales_warning', ['count' => 1]));
});

it('can record cash movement through livewire modal', function () {
    $staff = ps();
    $shift = makeCloseShift($staff, float: 10000);

    Livewire::actingAs($staff)
        ->test(ShiftsClose::class)
        ->call('openCashMovementModal')
        ->set('movementType', 'cash_in')
        ->set('movementAmount', '25.50')
        ->set('movementReason', 'Customer tip added to register')
        ->call('submitCashMovement')
        ->assertSet('showCashMovementModal', false)
        ->assertSet('movementError', null);

    expect(ShiftCashMovement::where('shift_id', $shift->id)->count())->toBe(1)
        ->and(ShiftCashMovement::where('shift_id', $shift->id)->first()->amount)->toBe(2550);
});

it('closes shift through livewire with redirect to summary', function () {
    $staff = ps();
    $shift = makeCloseShift($staff, float: 10000);

    Livewire::actingAs($staff)
        ->test(ShiftsClose::class)
        ->set('counted.cash', '100.00')
        ->call('closeShift')
        ->assertRedirect(route('shifts.summary', $shift));

    $shift->refresh();
    expect($shift->isClosed())->toBeTrue()
        ->and($shift->variance)->toBe(0);
});

it('shows summary view with reconciliation figures', function () {
    $staff = ps();
    $shift = makeCloseShift($staff, float: 10000);
    app(CloseShift::class)->execute($staff, $shift, ['cash' => 10000]);

    actAs($staff);
    $response = test()->get(route('shifts.summary', $shift));
    $response->assertOk()
        ->assertSee(__('shifts.summary_title'))
        ->assertSee($shift->till->name)
        ->assertSee('100.00 ETB');
});
