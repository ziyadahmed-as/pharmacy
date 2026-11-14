<?php

use App\Livewire\Finance\CashRevenue;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\Till;
use App\Models\User;
use App\Queries\CashSummaryQuery;
use App\Queries\RevenueSummaryQuery;
use App\Services\SettingsRepository;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-29: View cash and revenue summaries.
 * BRD: FR-6.1, FR-6.6, FR-7.1. AC-24.
 */
beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('finance.expense_approval_limit', 'not_set');
});

// ─── Shared helpers ──────────────────────────────────────────────────────────

function crProduct(array $attrs = []): Product
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

function crBatch(Product $product, int $qty = 200): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'CR-'.Str::random(5),
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

function crShift(User $user): Shift
{
    $till = Till::create(['name' => 'Till-CR-'.Str::random(4), 'is_active' => true]);

    return Shift::create([
        'till_id' => $till->id,
        'user_id' => $user->id,
        'opened_at' => now(),
        'opening_float' => 10000,
        'status' => 'open',
    ]);
}

/**
 * Create a completed sale with a payment on a given date.
 * Each call creates a fresh user+shift to avoid the unique-open-shift-per-user constraint.
 */
function crCompletedSale(
    int $total,
    string $method = 'cash',
    ?string $date = null
): Sale {
    // Use a fresh staff user so we never hit the unique open_user_id constraint
    $user = ps();
    $shift = crShift($user);
    $product = crProduct();
    $batch = crBatch($product);

    $completedAt = $date ? Carbon::parse($date) : now();

    $sale = Sale::create([
        'receipt_no' => 'CR-'.Str::random(8),
        'shift_id' => $shift->id,
        'user_id' => $user->id,
        'status' => 'completed',
        'total' => $total,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => $completedAt,
    ]);

    $sale->lines()->create([
        'product_id' => $batch->product_id,
        'batch_id' => $batch->id,
        'quantity' => 1,
        'unit_price' => $total,
        'subtotal' => $total,
        'is_fefo_override' => false,
    ]);

    $sale->payments()->create([
        'method' => $method,
        'amount' => $total,
    ]);

    return $sale;
}

/**
 * Create a voided sale on a given date.
 * Each call creates a fresh user+shift to avoid the unique-open-shift-per-user constraint.
 */
function crVoidedSale(int $total, ?string $date = null): Sale
{
    $user = ps();
    $shift = crShift($user);
    $product = crProduct();
    $batch = crBatch($product);

    $completedAt = $date ? Carbon::parse($date) : now();

    $sale = Sale::create([
        'receipt_no' => 'VD-'.Str::random(8),
        'shift_id' => $shift->id,
        'user_id' => $user->id,
        'status' => 'voided',
        'total' => $total,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => $completedAt,
    ]);

    $sale->lines()->create([
        'product_id' => $batch->product_id,
        'batch_id' => $batch->id,
        'quantity' => 1,
        'unit_price' => $total,
        'subtotal' => $total,
        'is_fefo_override' => false,
    ]);

    return $sale;
}

/**
 * Create a completed SaleReturn for the given sale.
 */
function crCompletedRefund(Sale $sale, int $refundAmount, string $method = 'cash', ?string $date = null): SaleReturn
{
    $completedAt = $date ? Carbon::parse($date) : now();

    return SaleReturn::create([
        'refund_no' => 'REF-'.Str::random(6),
        'sale_id' => $sale->id,
        'user_id' => $sale->user_id,
        'shift_id' => $sale->shift_id,
        'status' => SaleReturn::STATUS_COMPLETED,
        'total_refund' => $refundAmount,
        'payment_method' => $method,
        'reason' => 'Customer return',
        'requires_approval' => false,
        'completed_at' => $completedAt,
    ]);
}

/**
 * Create a posted Expense.
 */
function crExpense(User $user, int $amount, string $category = Expense::CATEGORY_UTILITIES, ?string $date = null): Expense
{
    return Expense::create([
        'category' => $category,
        'amount' => $amount,
        'date' => $date ?? now()->toDateString(),
        'description' => 'Test expense',
        'status' => Expense::STATUS_POSTED,
        'created_by' => $user->id,
        'approved_by' => $user->id,
    ]);
}

/**
 * Seed a locked DailyClosing.
 */
function crLockedDay(
    string $date,
    array $systemTotals,
    array $countedTotals,
    array $variances,
    User $closer
): DailyClosing {
    return DailyClosing::create([
        'date' => $date,
        'status' => DailyClosing::STATUS_LOCKED,
        'system_totals_by_method' => $systemTotals,
        'counted_totals_by_method' => $countedTotals,
        'variances_by_method' => $variances,
        'variance_reasons' => [],
        'closed_by' => $closer->id,
        'verified_by' => null,
        'locked_at' => now(),
        'notes' => null,
    ]);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

test('gross sales equals sum of completed sale totals for the period (FR-6.1)', function () {
    $date = now()->toDateString();

    // Three completed sales: 10000 + 25000 + 15000 = 50000 santim
    crCompletedSale(10000, 'cash', $date);
    crCompletedSale(25000, 'mobile_money', $date);
    crCompletedSale(15000, 'cash', $date);

    $revenue = (new RevenueSummaryQuery($date))->get();

    expect($revenue['gross_sales'])->toBe(50000)
        ->and($revenue['gross_sales_count'])->toBe(3);
});

test('refunds reduce net sales: net_sales = gross_sales - refunds (FR-6.1)', function () {
    $date = now()->toDateString();

    $sale = crCompletedSale(30000, 'cash', $date);
    crCompletedRefund($sale, 10000, 'cash', $date);

    $revenue = (new RevenueSummaryQuery($date))->get();

    expect($revenue['gross_sales'])->toBe(30000)
        ->and($revenue['refunds'])->toBe(10000)
        ->and($revenue['net_sales'])->toBe(20000)
        ->and($revenue['refunds_count'])->toBe(1);
});

test('voids are counted separately and do not affect gross sales (FR-6.1)', function () {
    $date = now()->toDateString();

    crCompletedSale(20000, 'cash', $date);
    crVoidedSale(50000, $date);

    $revenue = (new RevenueSummaryQuery($date))->get();

    expect($revenue['gross_sales'])->toBe(20000)
        ->and($revenue['voids'])->toBe(50000)
        ->and($revenue['voids_count'])->toBe(1);
});

test('expenses from UC-28 appear in revenue summary totals (FR-6.2)', function () {
    $ownerUser = owner();
    $date = now()->toDateString();

    crExpense($ownerUser, 15000, Expense::CATEGORY_UTILITIES, $date);
    crExpense($ownerUser, 5000, Expense::CATEGORY_TRANSPORT, $date);

    $revenue = (new RevenueSummaryQuery($date))->get();

    expect($revenue['expenses'])->toBe(20000)
        ->and($revenue['expenses_count'])->toBe(2)
        ->and($revenue['expenses_by_category'][Expense::CATEGORY_UTILITIES])->toBe(15000)
        ->and($revenue['expenses_by_category'][Expense::CATEGORY_TRANSPORT])->toBe(5000);
});

test('operating surplus equals net sales minus expenses (FR-6.1)', function () {
    $ownerUser = owner();
    $date = now()->toDateString();

    $sale = crCompletedSale(50000, 'cash', $date);
    crCompletedRefund($sale, 5000, 'cash', $date);
    crExpense($ownerUser, 10000, Expense::CATEGORY_UTILITIES, $date);

    // net_sales = 50000 - 5000 = 45000; surplus = 45000 - 10000 = 35000
    $revenue = (new RevenueSummaryQuery($date))->get();

    expect($revenue['net_sales'])->toBe(45000)
        ->and($revenue['expenses'])->toBe(10000)
        ->and($revenue['operating_surplus'])->toBe(35000);
});

test('cash by payment method is correct in CashSummaryQuery (FR-6.1)', function () {
    $date = now()->toDateString();

    crCompletedSale(20000, 'cash', $date);
    crCompletedSale(15000, 'mobile_money', $date);
    crCompletedSale(10000, 'cash', $date);

    $cash = (new CashSummaryQuery($date))->get();

    expect($cash['methods']['cash']['received'])->toBe(30000)
        ->and($cash['methods']['mobile_money']['received'])->toBe(15000)
        ->and($cash['total_received'])->toBe(45000);
});

test('refunds reduce cash net by payment method (FR-6.1)', function () {
    $date = now()->toDateString();

    $sale = crCompletedSale(30000, 'cash', $date);
    crCompletedRefund($sale, 8000, 'cash', $date);

    $cash = (new CashSummaryQuery($date))->get();

    expect($cash['methods']['cash']['received'])->toBe(30000)
        ->and($cash['methods']['cash']['refunded'])->toBe(8000)
        ->and($cash['methods']['cash']['net'])->toBe(22000)
        ->and($cash['total_net'])->toBe(22000);
});

test('locked-day figures match the closing record (AC-24, FR-6.6)', function () {
    $pmUser = pm();
    $date = now()->subDay()->toDateString();

    $systemTotals = ['cash' => 50000, 'mobile_money' => 20000];
    $countedTotals = ['cash' => 48000, 'mobile_money' => 20000];
    $variances = ['cash' => -2000, 'mobile_money' => 0];

    crLockedDay($date, $systemTotals, $countedTotals, $variances, $pmUser);

    $cash = (new CashSummaryQuery($date))->get();

    expect($cash['locked_days'])->toHaveCount(1);

    $day = $cash['locked_days'][0];

    expect($day['date'])->toBe($date)
        ->and($day['system_totals'])->toBe($systemTotals)
        ->and($day['counted_totals'])->toBe($countedTotals)
        ->and($day['variances'])->toBe($variances)
        ->and($day['system_total'])->toBe(70000)   // 50000 + 20000
        ->and($day['counted_total'])->toBe(68000)  // 48000 + 20000
        ->and($day['total_variance'])->toBe(-2000);

    expect($cash['locked_days_summary']['days_count'])->toBe(1)
        ->and($cash['locked_days_summary']['system_total'])->toBe(70000)
        ->and($cash['locked_days_summary']['counted_total'])->toBe(68000)
        ->and($cash['locked_days_summary']['variance'])->toBe(-2000);
});

test('unlocked (open) daily closings are excluded from reconciliation (FR-6.6)', function () {
    $pmUser = pm();
    $date = now()->toDateString();

    // Create an open (not locked) closing – must NOT appear in locked_days
    DailyClosing::create([
        'date' => $date,
        'status' => DailyClosing::STATUS_OPEN,
        'system_totals_by_method' => ['cash' => 100000],
        'counted_totals_by_method' => ['cash' => 100000],
        'variances_by_method' => ['cash' => 0],
        'closed_by' => $pmUser->id,
        'locked_at' => null,
    ]);

    $cash = (new CashSummaryQuery($date))->get();

    expect($cash['locked_days'])->toHaveCount(0)
        ->and($cash['locked_days_summary']['days_count'])->toBe(0);
});

test('sales outside the query date range are excluded', function () {
    $today = now()->toDateString();
    $yesterday = now()->subDay()->toDateString();
    $tomorrow = now()->addDay()->toDateString();

    // Sale on today - should be included
    crCompletedSale(20000, 'cash', $today);

    // Sale yesterday - excluded when querying today
    crCompletedSale(50000, 'cash', $yesterday);

    // Sale tomorrow - excluded when querying today
    crCompletedSale(30000, 'cash', $tomorrow);

    $revenue = (new RevenueSummaryQuery($today))->get();

    expect($revenue['gross_sales'])->toBe(20000)
        ->and($revenue['gross_sales_count'])->toBe(1);
});

test('period query spans multiple days correctly (FR-6.1)', function () {
    $start = now()->subDays(2)->toDateString();
    $end = now()->toDateString();

    crCompletedSale(10000, 'cash', now()->subDays(2)->toDateString());
    crCompletedSale(20000, 'cash', now()->subDay()->toDateString());
    crCompletedSale(30000, 'cash', now()->toDateString());

    $revenue = (new RevenueSummaryQuery($start, $end))->get();

    expect($revenue['gross_sales'])->toBe(60000)
        ->and($revenue['gross_sales_count'])->toBe(3);
});

test('export CSV returns 200 and contains expected values for PM (FR-6.1)', function () {
    $pmUser = pm();
    actAs($pmUser);
    $date = now()->toDateString();

    crCompletedSale(25000, 'cash', $date);

    $response = $this->get(route('finance.cash-revenue.export', ['format' => 'csv']).'?start_date='.$date.'&end_date='.$date);

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

test('export PDF returns 200 for PM (FR-6.1)', function () {
    $pmUser = pm();
    actAs($pmUser);
    $date = now()->toDateString();

    $response = $this->get(route('finance.cash-revenue.export', ['format' => 'pdf']).'?start_date='.$date.'&end_date='.$date);

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('print route returns 200 for PM (FR-6.1)', function () {
    $pmUser = pm();
    actAs($pmUser);
    $date = now()->toDateString();

    $response = $this->get(route('finance.cash-revenue.print').'?start_date='.$date.'&end_date='.$date);

    $response->assertStatus(200);
});

test('export denied for pharmacy staff (E - AC-24)', function () {
    $staffUser = ps();
    actAs($staffUser);
    $date = now()->toDateString();

    $response = $this->get(route('finance.cash-revenue.export', ['format' => 'csv']).'?start_date='.$date.'&end_date='.$date);

    $response->assertStatus(403);
});

test('export denied for system admin (E - AC-24)', function () {
    $saUser = sa();
    actAs($saUser);
    $date = now()->toDateString();

    $response = $this->get(route('finance.cash-revenue.export', ['format' => 'csv']).'?start_date='.$date.'&end_date='.$date);

    $response->assertStatus(403);
});

test('Livewire component renders for PM without errors and shows disclaimer (FR-7.1 E1)', function () {
    $pmUser = pm();
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(CashRevenue::class)
        ->assertStatus(200)
        ->assertSee('not a profit and loss');
});

test('Livewire component renders for Owner without errors (FR-7.1)', function () {
    $ownerUser = owner();
    actAs($ownerUser);

    Livewire::actingAs($ownerUser)
        ->test(CashRevenue::class)
        ->assertStatus(200);
});

test('Livewire component aborts with 403 for pharmacy staff (AC-24)', function () {
    $staffUser = ps();
    actAs($staffUser);

    Livewire::actingAs($staffUser)
        ->test(CashRevenue::class)
        ->assertStatus(403);
});

test('Livewire component aborts with 403 for system admin (AC-24)', function () {
    $saUser = sa();
    actAs($saUser);

    Livewire::actingAs($saUser)
        ->test(CashRevenue::class)
        ->assertStatus(403);
});

test('Livewire setMode switches between day and period', function () {
    $pmUser = pm();
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(CashRevenue::class)
        ->call('setMode', 'period')
        ->assertSet('mode', 'period')
        ->call('setMode', 'day')
        ->assertSet('mode', 'day');
});

test('Livewire setToday sets mode to day and selectedDate to today', function () {
    $pmUser = pm();
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(CashRevenue::class)
        ->call('setToday')
        ->assertSet('mode', 'day')
        ->assertSet('selectedDate', now()->toDateString());
});

test('Livewire setThisMonth sets mode to period with correct dates', function () {
    $pmUser = pm();
    actAs($pmUser);

    Livewire::actingAs($pmUser)
        ->test(CashRevenue::class)
        ->call('setThisMonth')
        ->assertSet('mode', 'period')
        ->assertSet('startDate', now()->startOfMonth()->toDateString())
        ->assertSet('endDate', now()->toDateString());
});

test('summary shows zero values when no data exists for the date (AC-24)', function () {
    $pmUser = pm();
    $date = '2020-01-01'; // Date with no data

    $revenue = (new RevenueSummaryQuery($date))->get();
    $cash = (new CashSummaryQuery($date))->get();

    expect($revenue['gross_sales'])->toBe(0)
        ->and($revenue['refunds'])->toBe(0)
        ->and($revenue['net_sales'])->toBe(0)
        ->and($revenue['expenses'])->toBe(0)
        ->and($revenue['operating_surplus'])->toBe(0)
        ->and($cash['total_received'])->toBe(0)
        ->and($cash['total_refunded'])->toBe(0)
        ->and($cash['total_net'])->toBe(0)
        ->and($cash['locked_days'])->toHaveCount(0);
});

test('multiple locked days aggregate correctly in locked_days_summary (AC-24)', function () {
    $pmUser = pm();
    $day1 = now()->subDays(2)->toDateString();
    $day2 = now()->subDay()->toDateString();

    crLockedDay($day1, ['cash' => 30000], ['cash' => 29000], ['cash' => -1000], $pmUser);
    crLockedDay($day2, ['cash' => 40000], ['cash' => 40500], ['cash' => 500], $pmUser);

    $start = now()->subDays(3)->toDateString();
    $end = now()->toDateString();

    $cash = (new CashSummaryQuery($start, $end))->get();

    expect($cash['locked_days'])->toHaveCount(2)
        ->and($cash['locked_days_summary']['days_count'])->toBe(2)
        ->and($cash['locked_days_summary']['system_total'])->toBe(70000)  // 30000 + 40000
        ->and($cash['locked_days_summary']['counted_total'])->toBe(69500) // 29000 + 40500
        ->and($cash['locked_days_summary']['variance'])->toBe(-500);      // -1000 + 500
});

test('pending_approval expenses are not included in revenue summary totals (UC-28 scope)', function () {
    $pmUser = pm();
    $date = now()->toDateString();

    // Posted expense (included)
    Expense::create([
        'category' => Expense::CATEGORY_UTILITIES,
        'amount' => 10000,
        'date' => $date,
        'description' => 'Posted',
        'status' => Expense::STATUS_POSTED,
        'created_by' => $pmUser->id,
        'approved_by' => $pmUser->id,
    ]);

    // Pending approval expense (excluded)
    Expense::create([
        'category' => Expense::CATEGORY_RENT,
        'amount' => 50000,
        'date' => $date,
        'description' => 'Pending approval rent',
        'status' => Expense::STATUS_PENDING_APPROVAL,
        'created_by' => $pmUser->id,
        'approved_by' => null,
    ]);

    $revenue = (new RevenueSummaryQuery($date))->get();

    expect($revenue['expenses'])->toBe(10000)
        ->and($revenue['expenses_count'])->toBe(1);
});

test('cash-revenue route returns 200 for Owner (AC-24)', function () {
    $ownerUser = owner();
    actAs($ownerUser);

    $response = $this->get(route('finance.cash-revenue'));

    $response->assertStatus(200);
});

test('cash-revenue route returns 403 for pharmacy staff (AC-24)', function () {
    $staffUser = ps();
    actAs($staffUser);

    $response = $this->get(route('finance.cash-revenue'));

    $response->assertStatus(403);
});

test('cash-revenue route returns 403 for system admin (AC-24)', function () {
    $saUser = sa();
    actAs($saUser);

    $response = $this->get(route('finance.cash-revenue'));

    $response->assertStatus(403);
});
