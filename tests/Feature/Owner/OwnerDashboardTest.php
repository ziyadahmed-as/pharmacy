<?php

use App\Enums\Role as RoleEnum;
use App\Livewire\Owner\Dashboard;
use App\Livewire\Owner\Tiles\CashTrendsTile;
use App\Livewire\Owner\Tiles\ExpiringItemsTile;
use App\Livewire\Owner\Tiles\LowStockTile;
use App\Livewire\Owner\Tiles\ProfitLossTile;
use App\Livewire\Owner\Tiles\SalesTodayTile;
use App\Livewire\Owner\Tiles\TopSellersTile;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shift;
use App\Models\Staff;
use App\Models\StockAlert;
use App\Models\Till;
use App\Models\User;
use App\Services\SettingsRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    seedRoles();
    app(SettingsRepository::class)->put('profile.name', 'City Central Pharmacy');
    app(SettingsRepository::class)->put('profile.phone', '+251911223344');
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createOwnerUser(string $phone = '+251911998877'): User
{
    $owner = owner();
    Staff::create([
        'user_id' => $owner->id,
        'name' => $owner->name,
        'phone' => $phone,
        'status' => Staff::ACTIVE,
        'hire_date' => now()->toDateString(),
    ]);

    return $owner->fresh(['staff']);
}

function createDashboardProduct(string $brand = 'Paracetamol 500mg', int $reorderLevel = 20): Product
{
    return Product::create([
        'brand' => $brand,
        'generic_name' => 'Paracetamol',
        'dosage_form' => 'tablet',
        'strength' => '500mg',
        'classification' => 'otc',
        'unit' => 'box',
        'reorder_level' => $reorderLevel,
        'is_active' => true,
    ]);
}

function createDashboardBatch(Product $product, int $qty = 50, int $unitCost = 400, ?string $expiry = null): Batch
{
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'BATCH-'.Str::random(6),
        'expiry_date' => $expiry ?? now()->addMonths(6)->toDateString(),
        'unit_cost' => $unitCost,
        'supplier_name' => 'Addis Pharma',
    ]);

    BatchStock::create([
        'batch_id' => $batch->id,
        'quantity' => $qty,
        'status' => 'sellable',
    ]);

    return $batch;
}

function createDashboardSale(User $user, Product $product, Batch $batch, int $qty, int $unitPrice, string $method = 'cash', ?string $completedAt = null): Sale
{
    $till = Till::firstOrCreate(['name' => 'Main Till'], ['is_active' => true]);
    $shift = Shift::firstOrCreate(
        ['user_id' => $user->id, 'status' => 'open'],
        ['till_id' => $till->id, 'opened_at' => now(), 'opening_float' => 5000]
    );

    $total = $qty * $unitPrice;
    $completed = $completedAt ? Carbon::parse($completedAt) : now();

    $sale = Sale::create([
        'receipt_no' => 'REC-'.Str::random(8),
        'shift_id' => $shift->id,
        'user_id' => $user->id,
        'status' => 'completed',
        'total' => $total,
        'idempotency_key' => Str::uuid()->toString(),
        'completed_at' => $completed,
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

// ─── Tests ───────────────────────────────────────────────────────────────────

it('denies access to guests, pharmacy staff, managers, and system admin (E1, FR-7.7)', function () {
    // Guest
    $this->get(route('owner.dashboard'))->assertRedirect(route('login'));

    // Pharmacy Staff
    $staff = ps();
    actAs($staff, RoleEnum::PharmacyStaff);
    $this->get(route('owner.dashboard'))->assertStatus(403);

    // Pharmacy Manager
    $manager = pm();
    actAs($manager, RoleEnum::PharmacyManager);
    $this->get(route('owner.dashboard'))->assertStatus(403);

    // System Admin (BR-06: technical admin never views business data)
    $admin = sa();
    actAs($admin, RoleEnum::SystemAdmin);
    $this->get(route('owner.dashboard'))->assertStatus(403);

    // Livewire mount abort check
    Livewire::actingAs($staff)
        ->test(Dashboard::class)
        ->assertStatus(403);
});

it('allows owner to access executive owner dashboard and renders mobile-responsive layout', function () {
    $owner = createOwnerUser();
    actAs($owner, RoleEnum::Owner);

    $response = $this->get(route('owner.dashboard'));
    $response->assertStatus(200);
    $response->assertSee(__('dashboard.owner.title'));
    $response->assertSee(__('dashboard.owner.subtitle'));

    // Check responsive grid structure
    $response->assertSee('grid-cols-1');
    $response->assertSee('lg:grid-cols-3');

    // Livewire component test
    Livewire::actingAs($owner)
        ->test(Dashboard::class)
        ->assertStatus(200)
        ->assertSee(__('dashboard.owner.send_daily_summary_btn'));
});

it('calculates today sales and refunds correctly on SalesTodayTile', function () {
    $owner = createOwnerUser();
    $staff = ps();

    $product = createDashboardProduct('Amoxicillin 500mg');
    $batch = createDashboardBatch($product, 100, 500);

    // Sale 1: 2 units @ 1000 cents (10.00 ETB) = 2000 cents
    $sale1 = createDashboardSale($staff, $product, $batch, 2, 1000, 'cash');

    // Sale 2: 3 units @ 1000 cents = 3000 cents
    $sale2 = createDashboardSale($staff, $product, $batch, 3, 1000, 'mobile_money');

    // Return on Sale 1: refund 500 cents
    SaleReturn::create([
        'refund_no' => 'REF-'.Str::random(6),
        'sale_id' => $sale1->id,
        'shift_id' => $sale1->shift_id,
        'user_id' => $staff->id,
        'status' => 'completed',
        'reason' => 'Customer changed mind',
        'total_refund' => 500,
        'completed_at' => now(),
    ]);

    // Livewire tile test (disable lazy loading to evaluate rendered metrics)
    Livewire::actingAs($owner)
        ->test(SalesTodayTile::class, ['lazy' => false])
        ->assertStatus(200)
        ->assertViewHas('grossSales', 5000)
        ->assertViewHas('salesCount', 2)
        ->assertViewHas('refunds', 500)
        ->assertViewHas('netSales', 4500)
        ->assertSee('45.00')
        ->assertSee('50.00 ETB')
        ->assertSee('-5.00 ETB');
});

it('identifies top selling products by volume on TopSellersTile', function () {
    $owner = createOwnerUser();
    $staff = ps();

    $prodA = createDashboardProduct('Product Alpha');
    $batchA = createDashboardBatch($prodA, 100, 300);

    $prodB = createDashboardProduct('Product Beta');
    $batchB = createDashboardBatch($prodB, 100, 400);

    // Sell 25 of Alpha and 5 of Beta
    createDashboardSale($staff, $prodA, $batchA, 25, 600, 'cash');
    createDashboardSale($staff, $prodB, $batchB, 5, 800, 'cash');

    Livewire::actingAs($owner)
        ->test(TopSellersTile::class, ['lazy' => false])
        ->assertStatus(200)
        ->assertSee('Product Alpha')
        ->assertSee('25')
        ->assertSee('Product Beta')
        ->assertSee('5');
});

it('reports active low stock alerts on LowStockTile', function () {
    $owner = createOwnerUser();
    $product1 = createDashboardProduct('Low Product 1', 50);
    $product2 = createDashboardProduct('Low Product 2', 50);

    StockAlert::create([
        'product_id' => $product1->id,
        'type' => 'low_stock',
        'message' => 'Stock is below reorder level',
        'status' => 'active',
        'dedupe_key' => 'low_'.$product1->id,
        'triggered_at' => now(),
    ]);

    StockAlert::create([
        'product_id' => $product2->id,
        'type' => 'critical_stock',
        'message' => 'Stock is critically low',
        'status' => 'active',
        'dedupe_key' => 'crit_'.$product2->id,
        'triggered_at' => now(),
    ]);

    // Resolved alert should be excluded
    StockAlert::create([
        'product_id' => $product2->id,
        'type' => 'low_stock',
        'message' => 'Old alert',
        'status' => 'resolved',
        'dedupe_key' => 'res_'.$product2->id,
        'triggered_at' => now()->subDays(5),
    ]);

    Livewire::actingAs($owner)
        ->test(LowStockTile::class, ['lazy' => false])
        ->assertStatus(200)
        ->assertViewHas('totalLowStockCount', 2)
        ->assertSee('2');
});

it('reports near-expiry and expired batches on ExpiringItemsTile', function () {
    $owner = createOwnerUser();
    $product = createDashboardProduct('Expiring Drug');

    // Batch 1: Expiring in 20 days with sellable stock
    createDashboardBatch($product, 10, 500, now()->addDays(20)->toDateString());

    // Batch 2: Expired 5 days ago with sellable stock
    createDashboardBatch($product, 5, 500, now()->subDays(5)->toDateString());

    // Batch 3: Expiring in 200 days (beyond 90-day threshold)
    createDashboardBatch($product, 50, 500, now()->addDays(200)->toDateString());

    Livewire::actingAs($owner)
        ->test(ExpiringItemsTile::class, ['lazy' => false])
        ->assertStatus(200)
        ->assertViewHas('nearExpiryCount', 1)
        ->assertViewHas('expiredCount', 1)
        ->assertSee('1')
        ->assertSee(__('dashboard.owner.near_expiry'));
});

it('calculates cash and digital tender with 7-day revenue on CashTrendsTile', function () {
    $owner = createOwnerUser();
    $staff = ps();
    $product = createDashboardProduct('Painkiller 100mg');
    $batch = createDashboardBatch($product, 100, 200);

    // Cash sale: 3000 cents
    createDashboardSale($staff, $product, $batch, 3, 1000, 'cash');

    // Telebirr sale: 2000 cents
    createDashboardSale($staff, $product, $batch, 2, 1000, 'telebirr');

    Livewire::actingAs($owner)
        ->test(CashTrendsTile::class, ['lazy' => false])
        ->assertStatus(200)
        ->assertViewHas('cashTender', 3000)
        ->assertViewHas('digitalTender', 2000)
        ->assertSee('30.00')
        ->assertSee('20.00');
});

it('restricts ProfitLossTile to Owner only and calculates gross profit, expenses, and net profit', function () {
    $owner = createOwnerUser();
    $staff = ps();

    $product = createDashboardProduct('Antibiotic X');
    // Unit cost = 400 cents (4.00 ETB)
    $batch = createDashboardBatch($product, 100, 400);

    // Sell 10 units @ 1000 cents (10.00 ETB) = 10000 cents revenue, 4000 cents COGS
    createDashboardSale($staff, $product, $batch, 10, 1000, 'cash');

    // Operating expense = 2500 cents (25.00 ETB)
    Expense::create([
        'description' => 'Courier Delivery Fee',
        'category' => 'transport',
        'date' => now()->toDateString(),
        'amount' => 2500,
        'status' => Expense::STATUS_POSTED,
        'created_by' => $owner->id,
    ]);

    // Owner can view tile and figures:
    // Net Revenue: 100.00 ETB (10000 cents)
    // COGS: 40.00 ETB (4000 cents)
    // Gross Profit: 60.00 ETB (6000 cents)
    // Expenses: 25.00 ETB (2500 cents)
    // Net Profit: 35.00 ETB (3500 cents)
    // Margin: 60.0%
    Livewire::actingAs($owner)
        ->test(ProfitLossTile::class, ['lazy' => false])
        ->assertStatus(200)
        ->assertViewHas('netRevenue', 10000)
        ->assertViewHas('cogs', 4000)
        ->assertViewHas('grossProfit', 6000)
        ->assertViewHas('expenses', 2500)
        ->assertViewHas('netProfit', 3500)
        ->assertViewHas('marginPercent', 60.0)
        ->assertSee('35.00')
        ->assertSee('60%')
        ->assertSee('40.00');

    // Non-owner (Pharmacy Staff) receives unauthorized tile view without financial figures
    Livewire::actingAs($staff)
        ->test(ProfitLossTile::class, ['lazy' => false])
        ->assertStatus(200)
        ->assertDontSee('35.00')
        ->assertDontSee('60%');
});

it('renders placeholder skeletons without blocking page loading', function () {
    $tiles = [
        new SalesTodayTile,
        new TopSellersTile,
        new LowStockTile,
        new ExpiringItemsTile,
        new CashTrendsTile,
        new ProfitLossTile,
    ];

    foreach ($tiles as $tile) {
        $placeholder = $tile->placeholder();
        expect($placeholder->name())->toBe('livewire.owner.tiles.placeholder');
    }

    // Default lazy mount renders placeholder
    Livewire::test(SalesTodayTile::class)
        ->assertSee('animate-pulse');
});

it('dispatches daily summary SMS from dashboard and records audit log (FR-9.2)', function () {
    $owner = createOwnerUser('+251911998877');
    actAs($owner, RoleEnum::Owner);

    Livewire::actingAs($owner)
        ->test(Dashboard::class)
        ->call('sendSummary')
        ->assertSet('errorMessage', null)
        ->assertSee(__('dashboard.owner.summary_sent_success', ['recipient' => '+251911998877']));

    // Verify audit log
    $log = AuditLog::where('action', 'owner.daily_summary.sent')->first();
    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($owner->id);
    expect($log->new_values['recipient'])->toBe('+251911998877');
    expect($log->new_values['success'])->toBeTrue();
});

it('executes console command pms:send-owner-daily-summary successfully', function () {
    $owner = createOwnerUser('+251911998877');

    $this->artisan('pms:send-owner-daily-summary', ['--channel' => 'sms'])
        ->expectsOutputToContain("Dispatching daily summary to Owner {$owner->name}...")
        ->expectsOutputToContain('Summary successfully sent to +251911998877.')
        ->assertExitCode(Command::SUCCESS);
});

it('handles console command when no owner user exists', function () {
    // No owners created
    $this->artisan('pms:send-owner-daily-summary')
        ->expectsOutput('No Owner user accounts found.')
        ->assertExitCode(Command::FAILURE);
});
