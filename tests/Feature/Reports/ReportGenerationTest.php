<?php

use App\Livewire\Reports\Index;
use App\Livewire\Reports\Run;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Category;
use App\Models\ControlledDrugRegister;
use App\Models\Customer;
use App\Models\Disposal;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\QuarantineRecord;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\Till;
use App\Models\User;
use App\Reports\ReportRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * UC-30: Generate and export a report.
 * BRD: FR-7.1 to 7.6, 2.19, 4.12. AC-29.
 */
beforeEach(fn () => seedRoles());

// ─── Test Helpers ────────────────────────────────────────────────────────────

function repProduct(array $attrs = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'dosage_form' => 'capsule',
        'strength' => '500mg',
        'classification' => 'rx',
        'unit' => 'capsule',
        'is_active' => true,
        'loose_allowed' => true,
    ], $attrs));
}

function repBatch(Product $product, int $qty = 100, int $unitCost = 500, ?string $expiry = null): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'BAT-'.Str::random(5),
        'expiry_date' => $expiry ?? now()->addYear()->format('Y-m-d'),
        'unit_cost' => $unitCost,
        'supplier_name' => 'National Pharma Corp',
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $qty,
        'status' => 'sellable',
    ]);

    return $batch;
}

function repShift(User $user): Shift
{
    $existing = Shift::where('user_id', $user->id)->where('status', 'open')->first();
    if ($existing) {
        return $existing;
    }

    $till = Till::create(['name' => 'Till-'.Str::random(5), 'is_active' => true]);

    return Shift::create([
        'till_id' => $till->id,
        'user_id' => $user->id,
        'opened_at' => now(),
        'opening_float' => 10000,
        'status' => 'open',
    ]);
}

function repSale(User $user, int $total, string $method = 'cash', ?string $date = null): Sale
{
    $product = repProduct();
    $batch = repBatch($product);
    $shift = repShift($user);

    $completedAt = $date ? Carbon::parse($date) : now();

    $sale = Sale::create([
        'receipt_no' => 'REC-'.Str::random(8),
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

// ─── Tests ───────────────────────────────────────────────────────────────────

test('System Admin is denied access to reports index and all report runs (E1, BR-06)', function () {
    $sa = sa();
    actAs($sa);

    $this->get(route('reports.index'))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'sales_summary']))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'inventory_current_stock']))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'audit_user_activity']))->assertStatus(403);
});

test('Pharmacy staff is denied access to sensitive compliance and audit reports (E1, A1)', function () {
    $staff = ps();
    actAs($staff);

    // Access to index is allowed for staff (operational scope)
    $this->get(route('reports.index'))->assertStatus(200);

    // Restricted compliance reports
    $this->get(route('reports.run', ['type' => 'compliance_controlled_drugs']))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'compliance_recalls']))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'compliance_disposals']))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'inventory_disposals_returns']))->assertStatus(403);

    // Restricted audit reports
    $this->get(route('reports.run', ['type' => 'audit_user_activity']))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'audit_voids']))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'audit_refunds']))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'audit_adjustments']))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'audit_disposals']))->assertStatus(403);
    $this->get(route('reports.run', ['type' => 'audit_price_changes']))->assertStatus(403);
});

test('Pharmacy staff is allowed access to operational stock, sales, and prescription reports (A1)', function () {
    $staff = ps();
    actAs($staff);

    $this->get(route('reports.run', ['type' => 'inventory_current_stock']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'inventory_low_stock']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'inventory_near_expiry']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'inventory_expired']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'inventory_batch_tracking']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'inventory_ledger']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'compliance_prescriptions']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'sales_summary']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'sales_by_staff']))->assertStatus(200);
});

test('Owner and Pharmacy Manager have access to all reports (FR-7.1 to 7.6)', function () {
    $owner = owner();
    actAs($owner);

    $this->get(route('reports.index'))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'sales_summary']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'compliance_controlled_drugs']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'audit_user_activity']))->assertStatus(200);

    $pm = pm();
    actAs($pm);
    $this->get(route('reports.run', ['type' => 'sales_summary']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'compliance_controlled_drugs']))->assertStatus(200);
    $this->get(route('reports.run', ['type' => 'audit_user_activity']))->assertStatus(200);
});

test('staff is strictly limited to their own sales and shift totals (A1)', function () {
    $staffA = ps();
    $staffB = ps();

    // Create sales for staff A and staff B
    repSale($staffA, 15000, 'cash');
    repSale($staffB, 45000, 'cash');

    $registry = app(ReportRegistry::class);
    $report = $registry->get('sales_by_staff');

    // Run query as staff A
    $resultA = $report->query([], $staffA);

    expect($resultA['row_count'])->toBe(1)
        ->and($resultA['rows'][0][0])->toBe($staffA->name)
        ->and($resultA['rows'][0][2])->toBe('150.00'); // 15,000 santim = 150.00 ETB

    // Run query as PM
    $pm = pm();
    $resultPM = $report->query([], $pm);
    expect($resultPM['row_count'])->toBe(2);
});

test('cost and profit columns are visible to Owner and PM, but completely omitted for Staff (STEP 5, BR-06)', function () {
    $pm = pm();
    $staff = ps();
    $registry = app(ReportRegistry::class);

    $salesSummary = $registry->get('sales_summary');
    $currentStock = $registry->get('inventory_current_stock');

    // PM headers contain cost and profit
    $pmSalesHeaders = $salesSummary->headers($pm);
    expect(in_array(__('reports.col_cost_of_goods'), $pmSalesHeaders))->toBeTrue()
        ->and(in_array(__('reports.col_gross_profit'), $pmSalesHeaders))->toBeTrue();

    $pmStockHeaders = $currentStock->headers($pm);
    expect(in_array(__('reports.col_unit_cost'), $pmStockHeaders))->toBeTrue()
        ->and(in_array(__('reports.col_valuation'), $pmStockHeaders))->toBeTrue();

    // Staff headers DO NOT contain cost or profit
    $staffSalesHeaders = $salesSummary->headers($staff);
    expect(in_array(__('reports.col_cost_of_goods'), $staffSalesHeaders))->toBeFalse()
        ->and(in_array(__('reports.col_gross_profit'), $staffSalesHeaders))->toBeFalse();

    $staffStockHeaders = $currentStock->headers($staff);
    expect(in_array(__('reports.col_unit_cost'), $staffStockHeaders))->toBeFalse()
        ->and(in_array(__('reports.col_valuation'), $staffStockHeaders))->toBeFalse();
});

test('sales summary report computes correct figures on seeded data (FR-7.1)', function () {
    $pm = pm();
    $sale = repSale($pm, 20000, 'cash');

    // Refund 5000 santim
    SaleReturn::create([
        'refund_no' => 'REF-001',
        'sale_id' => $sale->id,
        'user_id' => $pm->id,
        'shift_id' => $sale->shift_id,
        'status' => SaleReturn::STATUS_COMPLETED,
        'total_refund' => 5000,
        'payment_method' => 'cash',
        'reason' => 'Customer returned item',
        'requires_approval' => false,
        'completed_at' => now(),
    ]);

    $report = app(ReportRegistry::class)->get('sales_summary');
    $result = $report->query(['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()], $pm);

    expect($result['summary']['total_gross_sales'])->toBe('200.00')
        ->and($result['summary']['total_refunds'])->toBe('50.00')
        ->and($result['summary']['total_net_sales'])->toBe('150.00');
});

test('compliance controlled drug register report returns correct figures (FR-4.4, FR-7.4)', function () {
    $pm = pm();
    $second = ps();
    $product = repProduct(['classification' => 'controlled']);
    $batch = repBatch($product);

    ControlledDrugRegister::create([
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'entry_type' => 'sale',
        'quantity' => 10,
        'running_balance' => 90,
        'patient_name' => 'Abebe Kebede',
        'prescriber_name' => 'Dr. Taye',
        'user_id' => $pm->id,
        'second_person_id' => $second->id,
    ]);

    $report = app(ReportRegistry::class)->get('compliance_controlled_drugs');
    $result = $report->query([], $pm);

    expect($result['row_count'])->toBe(1)
        ->and($result['rows'][0][1])->toBe($product->brand)
        ->and($result['rows'][0][4])->toBe('10')
        ->and($result['rows'][0][6])->toBe('Abebe Kebede')
        ->and($result['rows'][0][9])->toBe($second->name);
});

test('disposal loss report returns financial loss and reason breakdown (FR-2.19)', function () {
    $pm = pm();
    $product = repProduct();
    $batch = repBatch($product, qty: 50, unitCost: 400); // 4.00 ETB unit cost

    $quarantine = QuarantineRecord::create([
        'batch_id' => $batch->id,
        'quantity' => 20,
        'reason' => QuarantineRecord::REASON_EXPIRED,
        'status' => QuarantineRecord::STATUS_OPEN,
        'created_by' => $pm->id,
    ]);

    Disposal::create([
        'batch_id' => $batch->id,
        'quarantine_record_id' => $quarantine->id,
        'quantity' => 20,
        'reason' => Disposal::REASON_EXPIRED,
        'method' => Disposal::METHOD_INCINERATION,
        'status' => Disposal::STATUS_APPROVED,
        'requested_by' => $pm->id,
        'certificate_no' => 'CERT-9988',
        'disposed_at' => now(),
    ]);

    $report = app(ReportRegistry::class)->get('compliance_disposals');
    $result = $report->query([], $pm);

    expect($result['row_count'])->toBe(1)
        ->and($result['rows'][0][1])->toBe('CERT-9988')
        ->and($result['rows'][0][4])->toBe('20')
        ->and($result['summary']['total_disposal_loss'])->toBe('80.00'); // 20 * 4.00 = 80.00 ETB
});

test('export to CSV returns 200, valid headers, and logs audit report.exported (STEP 3, AC-29)', function () {
    $pm = pm();
    actAs($pm);

    repSale($pm, 10000);

    $response = $this->get(route('reports.export', ['type' => 'sales_summary', 'format' => 'csv']));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    // Audit log check
    expect(AuditLog::where('action', 'report.exported')->exists())->toBeTrue();
});

test('export to PDF returns 200 and application/pdf (STEP 3, AC-29)', function () {
    $pm = pm();
    actAs($pm);

    repSale($pm, 10000);

    $response = $this->get(route('reports.export', ['type' => 'sales_summary', 'format' => 'pdf']));

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('print view returns 200 with pharmacy name and report table (STEP 3)', function () {
    $pm = pm();
    actAs($pm);

    repSale($pm, 10000);

    $response = $this->get(route('reports.print', ['type' => 'sales_summary']));

    $response->assertStatus(200);
    $response->assertSee('Sales Summary');
});

test('Livewire Index renders all allowed reports for user', function () {
    $pm = pm();
    actAs($pm);

    Livewire::test(Index::class)
        ->assertStatus(200)
        ->assertSee('Sales Summary')
        ->assertSee('Current Stock on Hand')
        ->assertSee('Controlled Drug Register')
        ->assertSee('User Activity Audit');
});

test('Livewire Run component executes and measures execution duration (NFR-1)', function () {
    $pm = pm();
    actAs($pm);

    Livewire::test(Run::class, ['type' => 'sales_summary'])
        ->assertStatus(200)
        ->assertSet('type', 'sales_summary')
        ->call('setThisMonth')
        ->assertStatus(200);
});

test('performance benchmark: daily and one-year reports run under NFR-1 target thresholds (NFR-1)', function () {
    $pm = pm();
    $product = repProduct();
    $batch = repBatch($product, qty: 100000);
    $shift = repShift($pm);

    // Seed 1,000 sales across a 365-day range to test annual query performance
    $now = now();
    $salesChunk = [];
    for ($i = 0; $i < 1000; $i++) {
        $date = $now->copy()->subDays($i % 365);
        $salesChunk[] = [
            'receipt_no' => 'BENCH-'.Str::random(8),
            'shift_id' => $shift->id,
            'user_id' => $pm->id,
            'status' => 'completed',
            'total' => 2500,
            'idempotency_key' => Str::uuid()->toString(),
            'completed_at' => $date->toDateTimeString(),
            'created_at' => $date->toDateTimeString(),
            'updated_at' => $date->toDateTimeString(),
        ];
    }
    DB::table('sales')->insert($salesChunk);

    $report = app(ReportRegistry::class)->get('sales_summary');

    // 1. Measure Daily report execution (target < 5 seconds)
    $startDaily = microtime(true);
    $report->query([
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
        'group_by' => 'day',
    ], $pm);
    $dailyDurationSec = microtime(true) - $startDaily;

    expect($dailyDurationSec)->toBeLessThan(5.0);

    // 2. Measure One-year report execution (target < 10 seconds)
    $startYear = microtime(true);
    $report->query([
        'start_date' => now()->subYear()->toDateString(),
        'end_date' => now()->toDateString(),
        'group_by' => 'month',
    ], $pm);
    $yearDurationSec = microtime(true) - $startYear;

    expect($yearDurationSec)->toBeLessThan(10.0);
});

test('sales by product and category reports compute correct aggregations (FR-7.1)', function () {
    $pm = pm();
    $cat = Category::create(['name' => 'Antibiotics']);
    $prod1 = repProduct(['brand' => 'Amoxil', 'category_id' => $cat->id]);
    $batch1 = repBatch($prod1, qty: 100, unitCost: 300);

    $shift = repShift($pm);
    $sale = Sale::create([
        'receipt_no' => 'REC-PROD-1',
        'shift_id' => $shift->id,
        'user_id' => $pm->id,
        'status' => 'completed',
        'total' => 2000,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
    ]);

    $sale->lines()->create([
        'product_id' => $prod1->id,
        'batch_id' => $batch1->id,
        'quantity' => 2,
        'unit_price' => 1000,
        'subtotal' => 2000,
        'is_fefo_override' => false,
    ]);

    $registry = app(ReportRegistry::class);

    // Test Sales by Product
    $prodReport = $registry->get('sales_by_product');
    $prodResult = $prodReport->query([], $pm);
    expect($prodResult['row_count'])->toBe(1)
        ->and($prodResult['rows'][0][0])->toBe('Amoxil')
        ->and($prodResult['rows'][0][3])->toBe('2')
        ->and($prodResult['rows'][0][4])->toBe('20.00'); // 2000 santim = 20.00 ETB

    // Test Sales by Category
    $catReport = $registry->get('sales_by_category');
    $catResult = $catReport->query([], $pm);
    expect($catResult['row_count'])->toBe(1)
        ->and($catResult['rows'][0][0])->toBe('Antibiotics')
        ->and($catResult['rows'][0][1])->toBe('2')
        ->and($catResult['rows'][0][2])->toBe('20.00');
});

test('inventory low stock report returns items below reorder level (FR-7.2)', function () {
    $pm = pm();
    $prod = repProduct(['brand' => 'Paracetamol', 'reorder_level' => 50, 'min_level' => 10]);
    // Batch stock is only 20, which is <= 50 reorder level
    repBatch($prod, qty: 20);

    $report = app(ReportRegistry::class)->get('inventory_low_stock');
    $result = $report->query([], $pm);

    expect($result['row_count'])->toBeGreaterThanOrEqual(1);
    $found = collect($result['rows'])->firstWhere(0, 'Paracetamol');
    expect($found)->not->toBeNull()
        ->and($found[3])->toBe('20')
        ->and($found[4])->toBe('50');
});

test('inventory expired and near-expiry reports return correct batches (FR-7.2)', function () {
    $pm = pm();
    $prod = repProduct(['brand' => 'Insulin']);

    // 1. Expired batch (expired 5 days ago)
    repBatch($prod, qty: 15, unitCost: 1000, expiry: now()->subDays(5)->format('Y-m-d'));

    // 2. Near-expiry batch (expires in 20 days)
    repBatch($prod, qty: 25, unitCost: 1000, expiry: now()->addDays(20)->format('Y-m-d'));

    $registry = app(ReportRegistry::class);

    $expiredReport = $registry->get('inventory_expired');
    $expiredResult = $expiredReport->query([], $pm);
    expect($expiredResult['row_count'])->toBe(1)
        ->and($expiredResult['rows'][0][0])->toBe('Insulin')
        ->and($expiredResult['rows'][0][5])->toBe('15');

    $nearExpiryReport = $registry->get('inventory_near_expiry');
    $nearExpiryResult = $nearExpiryReport->query(['threshold_days' => 30], $pm);
    expect($nearExpiryResult['row_count'])->toBe(1)
        ->and($nearExpiryResult['rows'][0][0])->toBe('Insulin')
        ->and($nearExpiryResult['rows'][0][4])->toBe('25');
});

test('compliance prescription report returns registered prescriptions (FR-4.12)', function () {
    $pm = pm();
    $customer = Customer::create([
        'name' => 'Sara Daniel',
        'phone' => '0911223344',
    ]);

    Prescription::create([
        'prescription_number' => 'RX-100200',
        'customer_id' => $customer->id,
        'prescriber_name' => 'Dr. Birhanu',
        'prescriber_registration_no' => 'REG-1234',
        'prescription_date' => now()->toDateString(),
        'status' => Prescription::STATUS_REGISTERED,
        'created_by' => $pm->id,
    ]);

    $report = app(ReportRegistry::class)->get('compliance_prescriptions');
    $result = $report->query([], $pm);

    expect($result['row_count'])->toBe(1)
        ->and($result['rows'][0][0])->toBe('RX-100200')
        ->and($result['rows'][0][2])->toBe('Sara Daniel')
        ->and($result['rows'][0][3])->toBe('Dr. Birhanu');
});

test('audit price changes report reflects updated selling prices (FR-1.5, FR-7.5)', function () {
    $pm = pm();
    $prod = repProduct(['brand' => 'Omeprazole']);

    ProductPrice::create([
        'product_id' => $prod->id,
        'price' => 3500, // 35.00 ETB
        'effective_from' => now(),
        'reason' => 'Annual supplier tariff update',
        'set_by' => $pm->id,
        'created_at' => now(),
    ]);

    $report = app(ReportRegistry::class)->get('audit_price_changes');
    $result = $report->query([], $pm);

    expect($result['row_count'])->toBe(1)
        ->and($result['rows'][0][1])->toBe('Omeprazole')
        ->and($result['rows'][0][2])->toBe('35.00')
        ->and($result['rows'][0][5])->toBe('Annual supplier tariff update');
});

test('export denied directly via URL when user does not have permission (E1)', function () {
    $staff = ps();
    actAs($staff);

    // Direct URL access to export a restricted report like controlled drugs or audit
    $this->get(route('reports.export', ['type' => 'compliance_controlled_drugs', 'format' => 'csv']))
        ->assertStatus(403);

    $this->get(route('reports.export', ['type' => 'audit_user_activity', 'format' => 'pdf']))
        ->assertStatus(403);

    $this->get(route('reports.print', ['type' => 'audit_user_activity']))
        ->assertStatus(403);
});

test('export CSV and PDF content matches screen query results (AC-29)', function () {
    $pm = pm();
    actAs($pm);

    $sale = repSale($pm, 12000, 'cash');

    // Run query directly (screen data)
    $report = app(ReportRegistry::class)->get('sales_summary');
    $queryResult = $report->query([
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ], $pm);

    // Check CSV Export output
    $csvResponse = $this->get(route('reports.export', [
        'type' => 'sales_summary',
        'format' => 'csv',
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $csvResponse->assertStatus(200);
    $csvContent = $csvResponse->streamedContent();

    // Check that headers and row figures match
    expect($csvContent)->toContain('Gross Sales (ETB)')
        ->and($csvContent)->toContain('120.00');

    // Check PDF Export output
    $pdfResponse = $this->get(route('reports.export', [
        'type' => 'sales_summary',
        'format' => 'pdf',
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
    ]));

    $pdfResponse->assertStatus(200);
    $pdfContent = $pdfResponse->getContent();
    expect($pdfContent)->toContain('%PDF-1.4')
        ->and($pdfContent)->toContain('Sales Summary');
});
