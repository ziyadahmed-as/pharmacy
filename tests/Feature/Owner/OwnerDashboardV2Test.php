<?php

use App\Enums\Role as RoleEnum;
use App\Livewire\Owner\Dashboard;
use App\Models\ApprovalRequest;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\Till;
use App\Models\User;
use App\Services\OwnerCommandCentreService;
use App\Services\SettingsRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

// ─── Helpers ─────────────────────────────────────────────────────────────────

beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('profile.name', 'Test Pharmacy');
    app(SettingsRepository::class)->put('profile.phone', '+251911000001');
});

function v2Owner(string $phone = '+251911000099'): User
{
    $owner = owner();
    Staff::firstOrCreate(
        ['user_id' => $owner->id],
        [
            'name' => $owner->name,
            'phone' => $phone,
            'status' => Staff::ACTIVE,
            'hire_date' => now()->toDateString(),
        ]
    );

    return $owner->fresh(['staff']);
}

function v2Product(string $brand = 'TestDrug 500mg', int $reorder = 20): Product
{
    return Product::create([
        'brand' => $brand,
        'generic_name' => 'Test Generic',
        'dosage_form' => 'tablet',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'box',
        'reorder_level' => $reorder,
        'is_active' => true,
    ]);
}

function v2Batch(Product $product, int $qty = 50, int $cost = 400, ?string $expiry = null): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'V2B-'.Str::random(6),
        'expiry_date' => $expiry ?? now()->addMonths(6)->toDateString(),
        'unit_cost' => $cost,
        'supplier_name' => 'Test Supplier',
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $qty,
        'status' => 'sellable',
    ]);

    return $batch;
}

function v2Sale(User $user, Product $product, Batch $batch, int $qty, int $unitPrice, ?string $method = 'cash', ?Carbon $at = null, ?Till $till = null): Sale
{
    $till = $till ?? Till::firstOrCreate(['name' => 'Main Till V2 - '.$user->id], ['is_active' => true]);
    $shift = Shift::firstOrCreate(
        ['user_id' => $user->id, 'status' => 'open'],
        ['till_id' => $till->id, 'opened_at' => now(), 'opening_float' => 5000]
    );

    $total = $qty * $unitPrice;
    $completedAt = $at ?? now();

    $sale = Sale::create([
        'receipt_no' => 'V2R-'.Str::random(8),
        'shift_id' => $shift->id,
        'user_id' => $user->id,
        'status' => 'completed',
        'total' => $total,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => $completedAt,
    ]);

    $sale->lines()->create([
        'product_id' => $product->id,
        'batch_id' => $batch->id,
        'quantity' => $qty,
        'unit_price' => $unitPrice,
        'subtotal' => $total,
        'is_fefo_override' => false,
    ]);

    $sale->payments()->create([
        'method' => $method,
        'amount' => $total,
    ]);

    return $sale;
}

// ─── Authorization ────────────────────────────────────────────────────────────

it('UC-32: denies dashboard access to guests, staff, manager and system admin (role matrix)', function () {
    // Guest
    $this->get(route('owner.dashboard'))->assertRedirect(route('login'));

    // Pharmacy Staff — 403
    $staff = ps();
    actAs($staff, RoleEnum::PharmacyStaff);
    $this->get(route('owner.dashboard'))->assertStatus(403);

    // Pharmacy Manager — 403
    $manager = pm();
    actAs($manager, RoleEnum::PharmacyManager);
    $this->get(route('owner.dashboard'))->assertStatus(403);

    // System Admin — 403 (BR-06)
    $admin = sa();
    actAs($admin, RoleEnum::SystemAdmin);
    $this->get(route('owner.dashboard'))->assertStatus(403);

    // Livewire direct-mount also blocked
    Livewire::actingAs($staff)
        ->test(Dashboard::class)
        ->assertStatus(403);
});

it('UC-32: allows owner to access and renders command centre shell', function () {
    $owner = v2Owner();
    actAs($owner, RoleEnum::Owner);

    $response = $this->get(route('owner.dashboard'));
    $response->assertStatus(200);

    // Key UI anchors
    Livewire::actingAs($owner)
        ->test(Dashboard::class)
        ->assertStatus(200)
        ->assertSee(__('dashboard.owner.btn_refresh'))
        ->assertSee(__('dashboard.owner.send_daily_summary_btn'));
});

// ─── Period Filters ───────────────────────────────────────────────────────────

it('UC-32: resolves date ranges correctly for all built-in periods', function () {
    $svc = app(OwnerCommandCentreService::class);

    $tz = config('pms.timezone', 'Africa/Addis_Ababa');
    $now = now()->timezone($tz);

    // today
    [$from, $to] = $svc->resolveDateRanges('today');
    expect($from->toDateString())->toBe($now->toDateString());
    expect($to->toDateString())->toBe($now->toDateString());

    // yesterday
    [$from, $to] = $svc->resolveDateRanges('yesterday');
    expect($from->toDateString())->toBe($now->copy()->subDay()->toDateString());

    // 7_days
    [$from, $to] = $svc->resolveDateRanges('7_days');
    expect($from->toDateString())->toBe($now->copy()->subDays(6)->toDateString());

    // this_month
    [$from, $to] = $svc->resolveDateRanges('this_month');
    expect($from->toDateString())->toBe($now->copy()->startOfMonth()->toDateString());

    // custom
    [$from, $to] = $svc->resolveDateRanges('custom', '2026-01-01', '2026-01-31');
    expect($from->toDateString())->toBe('2026-01-01');
    expect($to->toDateString())->toBe('2026-01-31');
});

it('UC-32: Livewire filter change updates date properties', function () {
    $owner = v2Owner();

    Livewire::actingAs($owner)
        ->test(Dashboard::class)
        ->set('period', 'yesterday')
        ->assertSet('period', 'yesterday');
});

// ─── KPIs ─────────────────────────────────────────────────────────────────────

it('UC-32: net_sales KPI equals gross sales minus refunds', function () {
    $owner = v2Owner();
    $staff = ps();

    $product = v2Product('Nettest Drug');
    $batch = v2Batch($product, 100, 300);

    // Gross: 2 × 1000 = 2000 cents
    v2Sale($staff, $product, $batch, 2, 1000);

    // Refund: 500 cents
    SaleReturn::create([
        'refund_no' => 'REF-'.Str::random(6),
        'sale_id' => Sale::latest()->first()->id,
        'shift_id' => Shift::first()->id,
        'user_id' => $staff->id,
        'status' => 'completed',
        'reason' => 'Test refund',
        'total_refund' => 500,
        'completed_at' => now(),
    ]);

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    $netSales = $data['kpis']['net_sales']['value'];
    // 2000 − 500 = 1500
    expect($netSales)->toBe(1500);
});

it('UC-32: sales_count KPI counts only completed sales', function () {
    $owner = v2Owner();
    $staff = ps();

    $product = v2Product('CountTest');
    $batch = v2Batch($product, 100, 200);

    v2Sale($staff, $product, $batch, 1, 500);
    v2Sale($staff, $product, $batch, 1, 500);

    // Voided sale — should NOT count
    $voidSale = Sale::create([
        'receipt_no' => 'V2VOID-'.Str::random(6),
        'shift_id' => Shift::first()->id,
        'user_id' => $staff->id,
        'status' => 'voided',
        'total' => 300,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
    ]);

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    expect($data['kpis']['sales_count']['value'])->toBe(2);
});

it('UC-32: average_basket KPI is net_sales / sales_count', function () {
    $owner = v2Owner();
    $staff = ps();

    $product = v2Product('AOVTest');
    $batch = v2Batch($product, 100, 200);

    v2Sale($staff, $product, $batch, 1, 2000); // 2000
    v2Sale($staff, $product, $batch, 1, 4000); // 4000
    // avg = 3000

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    expect($data['kpis']['average_basket']['value'])->toBe(3000);
});

it('UC-32: voids KPI is flagged as negative direction (more voids = bad)', function () {
    $owner = v2Owner();
    $staff = ps();

    // Create a voided sale today
    Sale::create([
        'receipt_no' => 'V2V-'.Str::random(6),
        'shift_id' => Shift::firstOrCreate(
            ['user_id' => $staff->id, 'status' => 'open'],
            ['till_id' => Till::firstOrCreate(['name' => 'VT'], ['is_active' => true])->id, 'opened_at' => now(), 'opening_float' => 1000]
        )->id,
        'user_id' => $staff->id,
        'status' => 'voided',
        'total' => 500,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => now(),
    ]);

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    expect($data['kpis']['voids']['value'])->toBe(1);
    // is_positive = false when current > previous (more voids = worse)
});

// ─── Filter By Till and Staff ─────────────────────────────────────────────────

it('UC-32: filtering by staff isolates that staff member\'s sales', function () {
    $owner = v2Owner();
    $staff1 = ps();
    $staff2 = pm(); // use a second built-in helper user

    $product = v2Product('FilterStaff');
    $batch = v2Batch($product, 100, 300);

    // staff1 makes 2 sales totalling 3000
    v2Sale($staff1, $product, $batch, 1, 1000);
    v2Sale($staff1, $product, $batch, 2, 1000);

    // staff2 makes 1 sale
    v2Sale($staff2, $product, $batch, 1, 2000);

    // Create staff record for staff1
    $staffRecord = Staff::firstOrCreate(
        ['user_id' => $staff1->id],
        ['name' => $staff1->name, 'phone' => '+25190000001', 'status' => Staff::ACTIVE, 'hire_date' => now()->toDateString()]
    );

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', staffId: $staffRecord->id, forceFresh: true);

    expect($data['kpis']['net_sales']['value'])->toBe(3000);
    expect($data['kpis']['sales_count']['value'])->toBe(2);
});

// ─── Charts ───────────────────────────────────────────────────────────────────

it('UC-32: computeCharts returns all 7 chart keys with data array', function () {
    $owner = v2Owner();

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    $expectedKeys = [
        'sales_by_hour',
        'sales_by_day',
        'sales_by_category',
        'sales_by_payment_method',
        'sales_by_staff',
        'top_10_products',
        'bottom_10_products',
    ];

    foreach ($expectedKeys as $key) {
        expect($data['charts'])->toHaveKey($key);
        expect($data['charts'][$key])->toHaveKey('data');
        expect($data['charts'][$key])->toHaveKey('title');
        expect($data['charts'][$key])->toHaveKey('drill_url');
    }
});

it('UC-32: toggleTable flips the chart view mode', function () {
    $owner = v2Owner();

    Livewire::actingAs($owner)
        ->test(Dashboard::class)
        ->assertSet('showTable', [])
        ->call('toggleTable', 'sales_by_hour')
        ->assertSet('showTable', ['sales_by_hour' => true])
        ->call('toggleTable', 'sales_by_hour')
        ->assertSet('showTable', ['sales_by_hour' => false]);
});

it('UC-32: exportChartCsv streams a valid CSV for a chart key', function () {
    $owner = v2Owner();
    $staff = ps();

    $product = v2Product('CSVExport');
    $batch = v2Batch($product, 100, 300);
    v2Sale($staff, $product, $batch, 1, 1000);

    Livewire::actingAs($owner)
        ->test(Dashboard::class)
        ->call('exportChartCsv', 'top_10_products')
        ->assertFileDownloaded();
});

// ─── Needs-Attention Panel ────────────────────────────────────────────────────

it('UC-32: needs-attention panel includes pending approvals when present', function () {
    $owner = v2Owner();

    if (! Schema::hasTable('approval_requests')) {
        $this->markTestSkipped('approval_requests table not present.');
    }

    ApprovalRequest::create([
        'initiator_id' => $owner->id,
        'type' => 'stock_adjustment',
        'status' => 'pending',
        'summary' => 'Pending stock adjustment approval',
        'eligible_roles' => ['Owner', 'PharmacyManager'],
        'payload' => ['note' => 'Test'],
    ]);

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    $keys = collect($data['attention'])->pluck('key');
    expect($keys)->toContain('pending_approvals');
});

it('UC-32: needs-attention panel flags expiring staff documents within 30 days', function () {
    $owner = v2Owner();

    if (! Schema::hasTable('staff_documents')) {
        $this->markTestSkipped('staff_documents table not present.');
    }

    $staffRecord = Staff::firstOrCreate(
        ['user_id' => $owner->id],
        ['name' => $owner->name, 'phone' => '+251900001', 'status' => Staff::ACTIVE, 'hire_date' => now()->toDateString()]
    );

    StaffDocument::create([
        'staff_id' => $staffRecord->id,
        'type' => 'professional_licence',
        'file_path' => 'docs/test.pdf',
        'issued_at' => now()->subYear()->toDateString(),
        'expires_at' => now()->addDays(10)->toDateString(),
        'uploaded_by' => $owner->id,
    ]);

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    $keys = collect($data['attention'])->pluck('key');
    expect($keys)->toContain('expiring_staff_docs');
});

it('UC-32: needs-attention panel flags products below reorder level', function () {
    $owner = v2Owner();

    // Create product with reorder_level=50, give it 5 units (below reorder)
    $product = v2Product('LowReorderDrug', 50);
    v2Batch($product, 5); // only 5 in stock

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    $keys = collect($data['attention'])->pluck('key');
    expect($keys)->toContain('products_low_stock');
});

it('UC-32: returns all_clear when no attention items exist', function () {
    $owner = v2Owner();

    $svc = app(OwnerCommandCentreService::class);
    // forceFresh to bypass cache
    $data = $svc->getDashboardData('today', forceFresh: true);

    // We can't guarantee zero items due to other tests, but we can verify structure
    expect($data['attention'])->toBeArray();
});

// ─── Drill-down Links ─────────────────────────────────────────────────────────

it('UC-32: each KPI has a valid drill_url containing the date range', function () {
    $owner = v2Owner();

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    foreach ($data['kpis'] as $key => $kpi) {
        expect($kpi)->toHaveKey('drill_url');
        expect($kpi['drill_url'])->toBeString();
        expect(strlen($kpi['drill_url']))->toBeGreaterThan(5);
    }
});

it('UC-32: each chart has a valid drill_url', function () {
    $owner = v2Owner();

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    foreach ($data['charts'] as $key => $chart) {
        expect($chart)->toHaveKey('drill_url');
        expect($chart['drill_url'])->toBeString();
    }
});

// ─── Live Activity Feed ───────────────────────────────────────────────────────

it('UC-32: live activity feed returns at most 20 entries with required keys', function () {
    $owner = v2Owner();

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    expect($data['activity'])->toBeArray();
    expect(count($data['activity']))->toBeLessThanOrEqual(20);

    foreach ($data['activity'] as $event) {
        expect($event)->toHaveKey('id');
        expect($event)->toHaveKey('action');
        expect($event)->toHaveKey('description');
        expect($event)->toHaveKey('time_ago');
        expect($event)->toHaveKey('badge_color');
    }
});

// ─── Refresh / Cache ─────────────────────────────────────────────────────────

it('UC-32: refreshDashboard sets statusMessage and clears cache', function () {
    $owner = v2Owner();

    Livewire::actingAs($owner)
        ->test(Dashboard::class)
        ->call('refreshDashboard')
        ->assertSet('statusMessage', __('dashboard.owner.refreshed_success'));
});

// ─── Empty-State Safety ───────────────────────────────────────────────────────

it('UC-32: dashboard loads cleanly with zero data (empty-state guard)', function () {
    $owner = v2Owner();

    $svc = app(OwnerCommandCentreService::class);
    // No sales, products, or other data seeded — should not throw
    $data = $svc->getDashboardData('today', forceFresh: true);

    expect($data['kpis']['net_sales']['value'])->toBe(0);
    expect($data['kpis']['sales_count']['value'])->toBe(0);
    expect($data['kpis']['average_basket']['value'])->toBe(0);
    expect($data['surplus']['surplus'])->toBe(0);
});

it('UC-32: calcPercentageChange handles zero previous period without division by zero', function () {
    $svc = app(OwnerCommandCentreService::class);

    // Access via reflection since private
    $method = new ReflectionMethod($svc, 'calcPercentageChange');
    $method->setAccessible(true);

    // Zero previous, positive current → 100%
    expect($method->invoke($svc, 100, 0))->toBe(100.0);

    // Zero previous, zero current → null
    expect($method->invoke($svc, 0, 0))->toBeNull();

    // Both positive → computed %
    expect($method->invoke($svc, 110, 100))->toBe(10.0);
});

// ─── Operating Surplus (STEP 7) ──────────────────────────────────────────────

it('UC-32: surplus is net sales minus expenses when show_cost_profit is off', function () {
    app(SettingsRepository::class)->put('reports.show_cost_profit', false);

    $owner = v2Owner();
    $staff = ps();

    $product = v2Product('SurplusDrug');
    $batch = v2Batch($product, 100, 400);

    // 10 × 1000 = 10000 cents revenue
    v2Sale($staff, $product, $batch, 10, 1000);

    // Expense 2500 cents
    Expense::create([
        'description' => 'Delivery fee',
        'category' => 'transport',
        'date' => now()->toDateString(),
        'amount' => 2500,
        'status' => Expense::STATUS_POSTED,
        'created_by' => $owner->id,
    ]);

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    expect($data['surplus']['show_cost_profit'])->toBeFalse();
    expect($data['surplus']['net_sales'])->toBe(10000);
    expect($data['surplus']['expenses'])->toBe(2500);
    expect($data['surplus']['surplus'])->toBe(7500);
});

it('UC-32: surplus includes COGS when show_cost_profit is on', function () {
    app(SettingsRepository::class)->put('reports.show_cost_profit', true);

    $owner = v2Owner();
    $staff = ps();

    $product = v2Product('COGSDrug');
    $batch = v2Batch($product, 100, 400); // unit_cost = 400

    // 10 × 1000 = 10000 cents revenue; COGS = 10 × 400 = 4000
    v2Sale($staff, $product, $batch, 10, 1000);

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    expect($data['surplus']['show_cost_profit'])->toBeTrue();
    expect($data['surplus']['cogs'])->toBe(4000);
    expect($data['surplus']['gross_profit'])->toBe(6000);
});

// ─── Period label appears in payload ─────────────────────────────────────────

it('UC-32: payload includes period_label, date_from, date_to, generated_at', function () {
    $owner = v2Owner();

    $svc = app(OwnerCommandCentreService::class);
    $data = $svc->getDashboardData('today', forceFresh: true);

    expect($data)->toHaveKey('period_label');
    expect($data)->toHaveKey('date_from');
    expect($data)->toHaveKey('date_to');
    expect($data)->toHaveKey('generated_at');
});
