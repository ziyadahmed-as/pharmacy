<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Closing\LockDay;
use App\Actions\Sales\CompleteSale;
use App\Actions\Sales\ProcessReturn;
use App\Actions\Sales\RequestVoid;
use App\Actions\Shifts\CloseShift;
use App\Actions\Shifts\OpenShift;
use App\Livewire\Closing\Day;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Till;
use App\Services\SettingsRepository;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * UC-18: Close and lock the day
 * BRD: FR-3.10, 6.1, 6.6; AC-24.
 */
beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('profile.name', 'Abyssinia Test Pharmacy');
    app(SettingsRepository::class)->put('sales.active_payment_methods', ['cash', 'mobile_money', 'card', 'bank_transfer']);
});

function createClosingTestProduct(): Product
{
    $product = Product::create([
        'brand' => 'Paracetamol 500mg',
        'generic_name' => 'Paracetamol',
        'dosage_form' => 'tablet',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'tablet',
        'is_active' => true,
        'loose_allowed' => true,
    ]);

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'BATCH-LOCK-001',
        'expiry_date' => Carbon::now()->addYear()->format('Y-m-d'),
        'unit_cost' => 500,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => 100,
        'status' => 'sellable',
    ]);

    app(SetSellingPrice::class)->execute(
        product: $product,
        price: 1000,
        effectiveFrom: now()->subMinute(),
        reason: 'Price set for testing',
        user: pm()
    );

    return $product;
}

function openClosingShift($user, int $floatSantim = 50000): Shift
{
    $till = Till::firstOrCreate(['name' => 'Till 1'], ['is_active' => true]);

    return app(OpenShift::class)->execute($user, $till, $floatSantim);
}

it('blocks day closing if an open shift exists (E1)', function () {
    $staff = ps();
    openClosingShift($staff, 50000);

    $manager = pm();

    expect(fn () => app(LockDay::class)->execute(
        $manager,
        now()->toDateString(),
        ['cash' => 50000],
        []
    ))->toThrow(RuntimeException::class, __('closing.open_shifts_exist_error', ['count' => 1]));
});

it('manager and owner can lock day when all shifts are closed', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    $product = createClosingTestProduct();

    // Make a cash sale of 2000 santim
    app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 2]],
        [['method' => 'cash', 'amount' => 2000]],
        'idempotency-day-lock-1'
    );

    // Close shift with counted cash = 52000
    app(CloseShift::class)->execute(
        $staff,
        $shift,
        ['cash' => 52000],
        null
    );

    // PM locks the day
    $manager = pm();
    $dailyClosing = app(LockDay::class)->execute(
        $manager,
        now()->toDateString(),
        ['cash' => 52000, 'mobile_money' => 0, 'card' => 0, 'bank_transfer' => 0],
        [],
        'Closing remarks',
        reauthConfirmed: true
    );

    expect($dailyClosing)->not->toBeNull()
        ->and($dailyClosing->status)->toBe('locked')
        ->and($dailyClosing->system_totals_by_method['cash'])->toBe(52000)
        ->and($dailyClosing->counted_totals_by_method['cash'])->toBe(52000)
        ->and($dailyClosing->variances_by_method['cash'])->toBe(0);

    // Verify audit log
    expect(
        AuditLog::where('action', 'day.locked')
            ->where('auditable_id', (string) $dailyClosing->id)
            ->exists()
    )->toBeTrue();
});

it('requires a mandatory reason when there is a tender variance', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    app(CloseShift::class)->execute($staff, $shift, ['cash' => 50000], null);

    $manager = pm();

    // Counted is 45000 (-5000 variance) without reason
    expect(fn () => app(LockDay::class)->execute(
        $manager,
        now()->toDateString(),
        ['cash' => 45000],
        [], // missing reason for cash variance
        reauthConfirmed: true
    ))->toThrow(InvalidArgumentException::class, __('closing.variance_reason_required_for_method', ['method' => 'Cash']));
});

it('accepts variance with a valid reason', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    app(CloseShift::class)->execute($staff, $shift, ['cash' => 50000], null);

    $manager = pm();

    $dailyClosing = app(LockDay::class)->execute(
        $manager,
        now()->toDateString(),
        ['cash' => 45000],
        ['cash' => 'Shortage in drawer, cash drop verified'],
        'Manager approved short',
        reauthConfirmed: true
    );

    expect($dailyClosing->variances_by_method['cash'])->toBe(-5000)
        ->and($dailyClosing->variance_reasons['cash'])->toBe('Shortage in drawer, cash drop verified');
});

it('prevents non-manager and system admin from locking the day', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    app(CloseShift::class)->execute($staff, $shift, ['cash' => 50000], null);

    // Staff cannot lock
    expect(fn () => app(LockDay::class)->execute(
        $staff,
        now()->toDateString(),
        ['cash' => 50000],
        [],
        reauthConfirmed: true
    ))->toThrow(AuthorizationException::class);

    // SA cannot lock
    $admin = sa();
    expect(fn () => app(LockDay::class)->execute(
        $admin,
        now()->toDateString(),
        ['cash' => 50000],
        [],
        reauthConfirmed: true
    ))->toThrow(AuthorizationException::class);
});

it('allows owner to lock the day', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    app(CloseShift::class)->execute($staff, $shift, ['cash' => 50000], null);

    $ownerUser = owner();
    $dailyClosing = app(LockDay::class)->execute(
        $ownerUser,
        now()->toDateString(),
        ['cash' => 50000],
        [],
        reauthConfirmed: true
    );

    expect($dailyClosing->status)->toBe('locked')
        ->and($dailyClosing->closed_by)->toBe($ownerUser->id);
});

it('enforces immutability on a locked daily closing record', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    app(CloseShift::class)->execute($staff, $shift, ['cash' => 50000], null);

    $manager = pm();
    $dailyClosing = app(LockDay::class)->execute(
        $manager,
        now()->toDateString(),
        ['cash' => 50000],
        [],
        reauthConfirmed: true
    );

    expect(function () use ($dailyClosing) {
        $dailyClosing->notes = 'Attempting illegal update';
        $dailyClosing->save();
    })->toThrow(RuntimeException::class, __('closing.locked_day_immutable'));
});

it('prevents opening a shift on a locked day', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    app(CloseShift::class)->execute($staff, $shift, ['cash' => 50000], null);

    $manager = pm();
    app(LockDay::class)->execute(
        $manager,
        now()->toDateString(),
        ['cash' => 50000],
        [],
        reauthConfirmed: true
    );

    expect(fn () => app(OpenShift::class)->execute($staff, $shift->till, 10000))
        ->toThrow(RuntimeException::class, __('closing.cannot_open_shift_on_locked_day'));
});

it('prevents sales on a locked day', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    $product = createClosingTestProduct();

    // 1. Sale before lock
    app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 1]],
        [['method' => 'cash', 'amount' => 1000]],
        'idempotency-pre-lock-sale'
    );

    // 2. Close shift and lock day
    app(CloseShift::class)->execute($staff, $shift, ['cash' => 51000], null);

    $manager = pm();
    app(LockDay::class)->execute(
        $manager,
        now()->toDateString(),
        ['cash' => 51000],
        [],
        reauthConfirmed: true
    );

    // Test sale refusal on locked day
    expect(fn () => app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 1]],
        [['method' => 'cash', 'amount' => 1000]],
        'idempotency-post-lock-sale'
    ))->toThrow(RuntimeException::class, __('closing.day_is_locked_error'));
});

it('refuses void and refund on a locked day', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    $product = createClosingTestProduct();

    $sale = app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 2]],
        [['method' => 'cash', 'amount' => 2000]],
        'idempotency-pre-lock-sale-2'
    );

    app(CloseShift::class)->execute($staff, $shift, ['cash' => 52000], null);

    $manager = pm();
    app(LockDay::class)->execute(
        $manager,
        now()->toDateString(),
        ['cash' => 52000],
        [],
        reauthConfirmed: true
    );

    // Refuse void
    expect(fn () => app(RequestVoid::class)->execute($staff, $sale->receipt_no, 'Customer mistake'))
        ->toThrow(RuntimeException::class, __('sales.void_error_locked_day'));

    // Refuse return
    expect(fn () => app(ProcessReturn::class)->execute(
        $staff,
        $sale->receipt_no,
        [['sale_line_id' => $sale->lines->first()->id, 'quantity' => 1, 'reason' => 'Defective']],
        'Defective item',
        $shift
    ))->toThrow(RuntimeException::class, __('sales.refund_error_locked_day'));
});

it('renders livewire day closing component and reflects variance requirements', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    app(CloseShift::class)->execute($staff, $shift, ['cash' => 50000], null);

    $manager = pm();

    Livewire::actingAs($manager)
        ->test(Day::class)
        ->assertSee(__('closing.title'))
        ->assertSee(__('closing.status_open'))
        ->set('counted.cash', 480.00) // 480.00 ETB = 48000 santim
        ->assertSee(__('closing.reason_required_label'));
});

it('renders daily closing printable report successfully', function () {
    $staff = ps();
    $shift = openClosingShift($staff, 50000);
    app(CloseShift::class)->execute($staff, $shift, ['cash' => 50000], null);

    $manager = pm();
    $dailyClosing = app(LockDay::class)->execute(
        $manager,
        now()->toDateString(),
        ['cash' => 50000],
        [],
        reauthConfirmed: true
    );

    test()->actingAs($manager)
        ->get(route('closing.report', $dailyClosing))
        ->assertStatus(200)
        ->assertSee(__('closing.report_title'))
        ->assertSee($manager->name);
});
