<?php

use App\Enums\Role as RoleEnum;
use App\Jobs\ProcessQueuedReportExport;
use App\Livewire\Reports\Index;
use App\Livewire\Reports\Run;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ReportFavourite;
use App\Models\ReportQueuedExport;
use App\Models\ReportSavedFilter;
use App\Models\ReportSchedule;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\Till;
use App\Reports\ReportRegistry;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function rcProduct(string $brand = 'Paracetamol 500mg', int $price = 500): Product
{
    return Product::create([
        'brand' => $brand,
        'generic_name' => 'Paracetamol',
        'dosage_form' => 'tablet',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'box',
        'selling_price' => $price,
        'is_active' => true,
    ]);
}

function rcBatch(Product $product, int $qty = 50, int $unitCost = 300): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'BAT-'.Str::random(5),
        'expiry_date' => now()->addYear()->format('Y-m-d'),
        'unit_cost' => $unitCost,
        'supplier_name' => 'National Pharma',
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $qty,
        'status' => 'sellable',
    ]);

    return $batch;
}

// ─── STEP 1: Report Centre Index & Favourites ─────────────────────────────────

it('rebuilds Reports Index with grouped categories, search, favourites and recent runs', function () {
    $owner = owner();
    actAs($owner, RoleEnum::Owner);

    // Initial render
    $comp = Livewire::actingAs($owner)
        ->test(Index::class)
        ->assertStatus(200)
        ->assertSee(__('reports.title'))
        ->assertSee(__('reports.category_sales'))
        ->assertSee(__('reports.category_inventory'))
        ->assertSee(__('reports.category_finance', [], 'en') ?: 'Finance')
        ->assertSee(__('reports.category_compliance'))
        ->assertSee(__('reports.category_audit'))
        ->assertSee(__('reports.category_staff'));

    // Toggle favourite
    $comp->call('toggleFavourite', 'sales_summary');

    expect(ReportFavourite::where('user_id', $owner->id)->where('report_key', 'sales_summary')->exists())->toBeTrue();

    // Re-render shows favourites
    $comp->call('$refresh')
        ->assertSee(__('reports.starred_reports', [], 'en') ?: 'Favourites');

    // Untoggle favourite
    $comp->call('toggleFavourite', 'sales_summary');
    expect(ReportFavourite::where('user_id', $owner->id)->where('report_key', 'sales_summary')->exists())->toBeFalse();
});

// ─── STEP 2: Registry Contains All 25+ Reports ────────────────────────────────

it('registers all 37 report definitions across sales, inventory, finance, compliance, audit and staff', function () {
    $registry = app(ReportRegistry::class);
    $all = $registry->all();

    expect(count($all))->toBeGreaterThanOrEqual(25);

    $expectedKeys = [
        // Original & Existing
        'sales_summary', 'sales_by_product', 'sales_by_category', 'sales_by_staff', 'sales_by_payment_method', 'sales_returns_refunds',
        'inventory_current_stock', 'inventory_low_stock', 'inventory_near_expiry', 'inventory_expired', 'inventory_batch_tracking', 'inventory_ledger', 'inventory_disposals_returns',
        'compliance_controlled_drugs', 'compliance_prescriptions', 'compliance_recalls', 'compliance_disposals',
        'audit_user_activity', 'audit_voids', 'audit_refunds', 'audit_adjustments', 'audit_disposals', 'audit_price_changes',
        'staff_monthly_attendance',
        // UC-33 Missing Reports
        'cash_shift_summary', 'daily_closing_history', 'expenses_by_category', 'credit_ageing',
        'payroll_summary', 'leave_attendance_summary', 'dispensing_by_staff', 'customer_purchase_history',
        'product_movement', 'approvals_log', 'stock_valuation', 'stocktake_variance', 'roster_vs_attendance',
    ];

    foreach ($expectedKeys as $key) {
        expect($registry->get($key))->not->toBeNull("Missing report definition for [{$key}]");
    }
});

// ─── STEP 2 Details: Test Execution of the New Reports on Seeded Data ─────────

it('runs cash and shift summary report correctly', function () {
    $owner = owner();
    $till = Till::create(['name' => 'Till-Cash1', 'is_active' => true]);
    $shift = Shift::create([
        'till_id' => $till->id,
        'user_id' => $owner->id,
        'opened_at' => now(),
        'opening_float' => 1000,
        'status' => 'open',
    ]);

    $report = app(ReportRegistry::class)->get('cash_shift_summary');
    $result = $report->query(['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()], $owner);

    expect($result['row_count'])->toBeGreaterThanOrEqual(1);
    expect($result['headers'])->toContain(__('reports.col_shift_id', [], 'en') ?: 'Shift #');
});

it('runs daily closing history report correctly', function () {
    $owner = owner();
    DailyClosing::create([
        'date' => now()->toDateString(),
        'status' => 'locked',
        'system_totals_by_method' => ['cash' => 1500000],
        'counted_totals_by_method' => ['cash' => 1500000],
        'variances_by_method' => ['cash' => 0],
        'closed_by' => $owner->id,
        'locked_at' => now(),
    ]);

    $report = app(ReportRegistry::class)->get('daily_closing_history');
    $result = $report->query(['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()], $owner);

    expect($result['row_count'])->toBeGreaterThanOrEqual(1);
});

it('runs expenses by category report correctly', function () {
    $owner = owner();
    Expense::create([
        'category' => 'transport',
        'amount' => 4500,
        'date' => now()->toDateString(),
        'description' => 'Delivery expense',
        'status' => Expense::STATUS_POSTED,
        'created_by' => $owner->id,
    ]);

    $report = app(ReportRegistry::class)->get('expenses_by_category');
    $result = $report->query(['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()], $owner);

    expect($result['row_count'])->toBeGreaterThanOrEqual(1);
});

it('runs credit receivables ageing report correctly', function () {
    $owner = owner();
    $cust = Customer::create(['name' => 'Hospital Partner', 'phone' => '+251911223344']);
    CreditAccount::create([
        'customer_id' => $cust->id,
        'credit_limit' => 500000,
        'current_balance' => 45000,
        'status' => 'active',
        'days_overdue' => 25,
        'created_by' => $owner->id,
    ]);

    $report = app(ReportRegistry::class)->get('credit_ageing');
    $result = $report->query([], $owner);

    expect($result['row_count'])->toBeGreaterThanOrEqual(1);
});

it('runs product movement and stock valuation reports correctly', function () {
    $owner = owner();
    $product = rcProduct('Fast Moving Paracetamol', 1000);
    rcBatch($product, 100, 600);

    // Product movement
    $movRep = app(ReportRegistry::class)->get('product_movement');
    $movRes = $movRep->query(['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->toDateString()], $owner);
    expect($movRes['row_count'])->toBeGreaterThanOrEqual(1);

    // Stock valuation
    $valRep = app(ReportRegistry::class)->get('stock_valuation');
    $valRes = $valRep->query([], $owner);
    expect($valRes['row_count'])->toBeGreaterThanOrEqual(1);
});

it('runs approvals log report correctly', function () {
    $owner = owner();
    ApprovalRequest::create([
        'type' => 'stock_adjustment',
        'summary' => 'Damaged ampoules write-off',
        'initiator_id' => $owner->id,
        'eligible_roles' => ['Owner', 'PharmacyManager'],
        'status' => 'approved',
        'approver_id' => $owner->id,
        'decided_at' => now(),
    ]);

    $rep = app(ReportRegistry::class)->get('approvals_log');
    $res = $rep->query(['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()], $owner);

    expect($res['row_count'])->toBeGreaterThanOrEqual(1);
});

// ─── STEP 3: Common Filters and Saved Filter Options ──────────────────────────

it('allows saving and loading user filter presets in Run component', function () {
    $owner = owner();

    $comp = Livewire::actingAs($owner)
        ->test(Run::class, ['type' => 'sales_summary'])
        ->set('startDate', '2026-05-01')
        ->set('endDate', '2026-05-31')
        ->set('filterPresetName', 'May 2026 Period')
        ->call('saveCurrentFilter')
        ->assertSee("Filter preset 'May 2026 Period' saved.");

    expect(ReportSavedFilter::where('user_id', $owner->id)->where('name', 'May 2026 Period')->exists())->toBeTrue();

    // Reset date and load preset
    $comp->set('startDate', '2026-01-01')
        ->call('loadSavedFilter', 'May 2026 Period')
        ->assertSet('startDate', '2026-05-01');
});

// ─── STEP 4: Client-side Sort, Pagination, Visibility & Queued Exports ────────

it('supports table sorting and toggling column visibility', function () {
    $owner = owner();

    Livewire::actingAs($owner)
        ->test(Run::class, ['type' => 'sales_summary'])
        ->call('sortBy', 1)
        ->assertSet('sortCol', 1)
        ->assertSet('sortDir', 'asc')
        ->call('sortBy', 1)
        ->assertSet('sortDir', 'desc')
        ->call('toggleColumn', 0)
        ->assertSet('visibleColumns.0', false);
});

it('handles background queued exports for large or requested datasets (STEP 4)', function () {
    Storage::fake('local');
    $owner = owner();

    $export = ReportQueuedExport::create([
        'user_id' => $owner->id,
        'report_key' => 'sales_summary',
        'format' => 'csv',
        'filters' => ['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()],
        'status' => ReportQueuedExport::STATUS_PENDING,
    ]);

    // Process job synchronously
    $job = new ProcessQueuedReportExport($export->id);
    $job->handle(app(ReportRegistry::class));

    $export->refresh();
    expect($export->status)->toBe(ReportQueuedExport::STATUS_COMPLETED);
    expect($export->file_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($export->file_path))->toBeTrue();

    // Test download route
    actAs($owner, RoleEnum::Owner);
    $response = test()->get(route('reports.export.download', ['id' => $export->id]));
    $response->assertStatus(200);
});

// ─── STEP 5: Report Scheduling & Audited Send ─────────────────────────────────

it('allows owner to schedule reports and dispatches through command (STEP 5)', function () {
    $owner = owner();

    Livewire::actingAs($owner)
        ->test(Run::class, ['type' => 'sales_summary'])
        ->set('scheduleFrequency', 'weekly')
        ->set('scheduleFormat', 'csv')
        ->set('scheduleRecipients', 'owner@pharmacy.et, manager@pharmacy.et')
        ->call('createSchedule')
        ->assertSet('feedbackMessage', 'Report schedule created successfully.');

    $schedule = ReportSchedule::where('report_key', 'sales_summary')->first();
    expect($schedule)->not->toBeNull();
    expect($schedule->frequency)->toBe('weekly');
    expect($schedule->recipient_emails)->toContain('owner@pharmacy.et');

    // Force schedule to be due for dispatch
    $schedule->update(['next_run_at' => now()->subMinute()]);

    // Run command
    test()->artisan('pms:send-scheduled-reports')
        ->assertExitCode(0);

    $log = AuditLog::where('action', 'report.scheduled_dispatched')->first();
    expect($log)->not->toBeNull();
    expect($log->metadata['report'])->toBe('sales_summary');
});

// ─── STEP 6: Role Matrix & Permissions ────────────────────────────────────────

it('enforces role matrix permissions across all reports', function () {
    $owner = owner();
    $manager = pm();
    $staff = ps();
    $admin = sa();

    // 1. System Admin is blocked from all report routes (BR-06)
    actAs($admin, RoleEnum::SystemAdmin);
    test()->get(route('reports.index'))->assertStatus(403);
    test()->get(route('reports.run', ['type' => 'sales_summary']))->assertStatus(403);

    // 2. Pharmacy Staff is blocked from sensitive reports
    actAs($staff, RoleEnum::PharmacyStaff);
    test()->get(route('reports.run', ['type' => 'daily_closing_history']))->assertStatus(403);
    test()->get(route('reports.run', ['type' => 'payroll_summary']))->assertStatus(403);
    test()->get(route('reports.run', ['type' => 'stock_valuation']))->assertStatus(403);

    // 3. Pharmacy Manager has access to operational and inventory reports
    actAs($manager, RoleEnum::PharmacyManager);
    test()->get(route('reports.run', ['type' => 'inventory_current_stock']))->assertStatus(200);
    test()->get(route('reports.run', ['type' => 'product_movement']))->assertStatus(200);
    test()->get(route('reports.run', ['type' => 'sales_summary']))->assertStatus(200);

    // 4. Owner has full access to all reports
    actAs($owner, RoleEnum::Owner);
    test()->get(route('reports.run', ['type' => 'stock_valuation']))->assertStatus(200);
    test()->get(route('reports.run', ['type' => 'payroll_summary']))->assertStatus(200);
    test()->get(route('reports.run', ['type' => 'credit_ageing']))->assertStatus(200);
});

// ─── Performance Budget (NFR-1) ───────────────────────────────────────────────

it('meets report execution performance budget (< 5 s for daily/monthly runs)', function () {
    $owner = owner();
    $registry = app(ReportRegistry::class);
    $report = $registry->get('sales_summary');

    $start = microtime(true);
    $report->query(['start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->toDateString()], $owner);
    $duration = microtime(true) - $start;

    expect($duration)->toBeLessThan(5.0);
});
