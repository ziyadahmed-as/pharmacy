<?php

use App\Actions\Catalogue\SetSellingPrice;
use App\Actions\Credits\RecordCreditPayment;
use App\Actions\Finance\RecordExpense;
use App\Actions\Sales\CompleteSale;
use App\Actions\Sales\ProcessReturn;
use App\Actions\Shifts\OpenShift;
use App\Approvals\Handlers\StockAdjustmentHandler;
use App\Models\AdjustmentRequest;
use App\Models\ApprovalRequest;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\Product;
use App\Models\SaleReturnLine;
use App\Models\Shift;
use App\Models\Till;
use Carbon\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    seedRoles();
    Carbon::setTestNow(); // reset
});

afterEach(function () {
    Carbon::setTestNow(); // reset
});

function createBusinessDateTestProduct(): Product
{
    $product = Product::create([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'dosage_form' => 'capsule',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'capsule',
        'is_active' => true,
        'loose_allowed' => true,
    ]);

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'B-BDAY-001',
        'expiry_date' => Carbon::now()->addYear()->format('Y-m-d'),
        'unit_cost' => 500,
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => 200,
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

function openBusinessDateShift($user): Shift
{
    $till = Till::firstOrCreate(['name' => 'Till BD-1'], ['is_active' => true]);

    return app(OpenShift::class)->execute($user, $till, 50000);
}

function lockBusinessDay(string $dateYmd, $closer): DailyClosing
{
    return DailyClosing::create([
        'date' => $dateYmd,
        'status' => DailyClosing::STATUS_LOCKED,
        'system_totals_by_method' => ['cash' => 0],
        'counted_totals_by_method' => ['cash' => 0],
        'variances_by_method' => ['cash' => 0],
        'closed_by' => $closer->id,
        'verified_by' => $closer->id,
        'locked_at' => now(),
    ]);
}

test('business_date helper converts UTC timestamp to Africa/Addis_Ababa date', function () {
    // 21:30 UTC on 2026-05-10 is 00:30 on 2026-05-11 in Addis Ababa (UTC+3)
    Carbon::setTestNow(Carbon::parse('2026-05-10 21:30:00', 'UTC'));
    expect(business_date())->toBe('2026-05-11')
        ->and(now()->toDateString())->toBe('2026-05-10'); // UTC is still 10th

    // 20:59 UTC on 2026-05-10 is 23:59 on 2026-05-10 in Addis Ababa
    Carbon::setTestNow(Carbon::parse('2026-05-10 20:59:00', 'UTC'));
    expect(business_date())->toBe('2026-05-10');

    // 00:30 UTC on 2026-05-10 is 03:30 on 2026-05-10 in Addis Ababa
    Carbon::setTestNow(Carbon::parse('2026-05-10 00:30:00', 'UTC'));
    expect(business_date())->toBe('2026-05-10');
});

test('a sale at 21:30 UTC checks against next day Addis date (2026-05-11) not UTC date (2026-05-10)', function () {
    $staff = ps();
    $manager = pm();

    // Lock 2026-05-10 (the UTC date)
    lockBusinessDay('2026-05-10', $manager);

    // Set time to 21:30 UTC on 2026-05-10 (= 00:30 on 2026-05-11 Addis Ababa)
    Carbon::setTestNow(Carbon::parse('2026-05-10 21:30:00', 'UTC'));

    // Open shift on the unlocked business day (2026-05-11)
    $shift = openBusinessDateShift($staff);
    $product = createBusinessDateTestProduct();

    // The sale should SUCCEED because in Addis Ababa it is 2026-05-11 (not locked),
    // even though UTC is 2026-05-10 (which is locked).
    $sale = app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 1]],
        [['method' => 'cash', 'amount' => 1000]],
        (string) Str::uuid()
    );

    expect($sale)->not->toBeNull()
        ->and($sale->status)->toBe('completed');

    // Now, if 2026-05-11 is also locked, subsequent sale MUST be blocked
    lockBusinessDay('2026-05-11', $manager);

    expect(fn () => app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 1]],
        [['method' => 'cash', 'amount' => 1000]],
        (string) Str::uuid()
    ))->toThrow(RuntimeException::class, __('closing.day_is_locked_error'));
});

test('a sale at 20:59 UTC checks against same day Addis date (2026-05-10) and is blocked if locked', function () {
    $staff = ps();
    $manager = pm();

    Carbon::setTestNow(Carbon::parse('2026-05-10 20:59:00', 'UTC'));

    // Lock 2026-05-10
    lockBusinessDay('2026-05-10', $manager);

    // Shift opening should be blocked on locked day
    $till = Till::firstOrCreate(['name' => 'Till BD-2059'], ['is_active' => true]);
    expect(fn () => app(OpenShift::class)->execute($staff, $till, 50000))
        ->toThrow(RuntimeException::class, __('closing.cannot_open_shift_on_locked_day'));
});

test('a sale at 00:30 UTC checks against same day Addis date (2026-05-10) and succeeds when unlocked', function () {
    $staff = ps();

    Carbon::setTestNow(Carbon::parse('2026-05-10 00:30:00', 'UTC'));

    $shift = openBusinessDateShift($staff);
    $product = createBusinessDateTestProduct();

    $sale = app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 1]],
        [['method' => 'cash', 'amount' => 1000]],
        (string) Str::uuid()
    );

    expect($sale)->not->toBeNull()
        ->and($sale->status)->toBe('completed');
});

test('a sale at 22:30 Addis time is checked against the Addis date', function () {
    $staff = ps();
    $manager = pm();

    // 22:30 Addis time on 2026-05-10 is 19:30 UTC on 2026-05-10
    Carbon::setTestNow(Carbon::createFromFormat('Y-m-d H:i:s', '2026-05-10 22:30:00', 'Africa/Addis_Ababa')->utc());

    // Day is not locked: sale succeeds
    $shift = openBusinessDateShift($staff);
    $product = createBusinessDateTestProduct();

    $sale = app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 1]],
        [['method' => 'cash', 'amount' => 1000]],
        (string) Str::uuid()
    );

    expect($sale)->not->toBeNull()
        ->and($sale->status)->toBe('completed');

    // Now lock 2026-05-10
    lockBusinessDay('2026-05-10', $manager);

    // Next sale at 22:30 Addis time is blocked
    expect(fn () => app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 1]],
        [['method' => 'cash', 'amount' => 1000]],
        (string) Str::uuid()
    ))->toThrow(RuntimeException::class, __('closing.day_is_locked_error'));
});

test('closing a day blocks a sale inside the transaction after shift is locked', function () {
    $staff = ps();
    $manager = pm();

    Carbon::setTestNow(Carbon::parse('2026-05-10 12:00:00', 'UTC'));

    $shift = openBusinessDateShift($staff);
    $product = createBusinessDateTestProduct();

    // Lock the day while the shift is still considered open (e.g. concurrent race)
    lockBusinessDay('2026-05-10', $manager);

    // Even if initial guards are bypassed, inside transaction day-lock check catches it
    expect(fn () => app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 1]],
        [['method' => 'cash', 'amount' => 1000]],
        (string) Str::uuid()
    ))->toThrow(RuntimeException::class, __('closing.day_is_locked_error'));
});

test('ProcessReturn blocks refund if today or original sale date is locked', function () {
    $staff = ps();
    $manager = pm();

    Carbon::setTestNow(Carbon::parse('2026-05-01 10:00:00', 'UTC'));
    $shift = openBusinessDateShift($staff);
    $product = createBusinessDateTestProduct();

    $sale = app(CompleteSale::class)->execute(
        $staff,
        [['product_id' => $product->id, 'quantity' => 2]],
        [['method' => 'cash', 'amount' => 2000]],
        (string) Str::uuid()
    );

    // Move to 2026-05-05
    Carbon::setTestNow(Carbon::parse('2026-05-05 10:00:00', 'UTC'));

    // Lock today (2026-05-05)
    lockBusinessDay('2026-05-05', $manager);

    $line = $sale->lines()->first();
    expect(fn () => app(ProcessReturn::class)->execute(
        $staff,
        $sale->receipt_no,
        [['sale_line_id' => $line->id, 'quantity' => 1, 'assessment' => SaleReturnLine::ASSESSMENT_RESALEABLE]],
        'Customer return'
    ))->toThrow(RuntimeException::class, __('closing.day_is_locked_error'));
});

test('RecordExpense blocks expense if date is locked', function () {
    $manager = pm();

    Carbon::setTestNow(Carbon::parse('2026-05-10 10:00:00', 'UTC'));
    lockBusinessDay('2026-05-10', $manager);

    expect(fn () => app(RecordExpense::class)->execute(
        $manager,
        [
            'category' => Expense::CATEGORY_UTILITIES,
            'amount' => 5000,
            'description' => 'Water bill',
            'date' => '2026-05-10',
        ]
    ))->toThrow(RuntimeException::class, __('closing.day_is_locked_error'));
});

test('RecordCreditPayment blocks payment if day is locked', function () {
    $manager = pm();

    Carbon::setTestNow(Carbon::parse('2026-05-10 10:00:00', 'UTC'));
    lockBusinessDay('2026-05-10', $manager);

    $customer = Customer::create([
        'name' => 'Credit Customer Test',
        'phone' => '0911223344',
    ]);

    $account = CreditAccount::create([
        'customer_id' => $customer->id,
        'credit_limit' => 100000,
        'balance' => 50000,
        'is_active' => true,
        'created_by' => $manager->id,
    ]);

    expect(fn () => app(RecordCreditPayment::class)->execute(
        $manager,
        $account,
        10000,
        'cash'
    ))->toThrow(RuntimeException::class, __('closing.day_is_locked_error'));
});

test('StockAdjustmentHandler blocks adjustment posting on a locked day', function () {
    $manager = pm();

    Carbon::setTestNow(Carbon::parse('2026-05-10 10:00:00', 'UTC'));
    $product = createBusinessDateTestProduct();
    $batch = $product->batches()->first();

    $adj = AdjustmentRequest::create([
        'batch_id' => $batch->id,
        'reason_type' => AdjustmentRequest::REASON_FOUND_STOCK,
        'quantity' => 5,
        'written_reason' => 'Audit found stock',
        'status' => AdjustmentRequest::STATUS_PENDING_APPROVAL,
        'requested_by' => $manager->id,
        'recount_done' => true,
    ]);

    $req = ApprovalRequest::create([
        'type' => 'stock.adjustment',
        'initiator_id' => $manager->id,
        'eligible_roles' => ['owner'],
        'status' => ApprovalRequest::PENDING,
        'expires_at' => now()->addDays(3),
        'payload' => ['adjustment_request_id' => $adj->id],
        'summary' => 'Found stock',
    ]);

    // Lock today
    lockBusinessDay('2026-05-10', $manager);

    $handler = new StockAdjustmentHandler;
    expect(fn () => $handler->onApproved($req, $manager))
        ->toThrow(RuntimeException::class, __('closing.day_is_locked_error'));
});
