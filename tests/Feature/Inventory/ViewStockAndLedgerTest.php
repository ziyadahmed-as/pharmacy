<?php

use App\Actions\Inventory\ConfirmStockReceipt;
use App\Actions\Inventory\SubmitStockReceipt;
use App\Livewire\Stock\Index as StockIndex;
use App\Livewire\Stock\Ledger as StockLedger;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\LedgerQuery;
use App\Services\StockMovementService;
use App\Services\StockOnHandQuery;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function makeStockProduct(array $attr = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Amoxicillin 500mg',
        'generic_name' => 'Amoxicillin',
        'strength' => '500mg',
        'dosage_form' => 'Capsule',
        'classification' => 'rx',
        'reorder_level' => 20,
        'is_active' => true,
    ], $attr));
}

test('stock by batch is correct after receipts', function () {
    $product = makeStockProduct();
    $pm = pm();

    $submit = new SubmitStockReceipt;
    $confirm = new ConfirmStockReceipt;

    $receipt = $submit->execute([
        'supplier_name' => 'MedTech Ethiopia',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'STOCK_TEST_01',
        'lines' => [
            [
                'product_id' => $product->id,
                'batch_no' => 'BATCH-A1',
                'manufacturing_date' => now()->subMonths(3)->format('Y-m-d'),
                'expiry_date' => now()->addMonths(18)->format('Y-m-d'),
                'quantity' => 100,
                'unit_cost' => 250, // 250 santim
                'flag' => 'ok',
            ],
            [
                'product_id' => $product->id,
                'batch_no' => 'BATCH-A2',
                'manufacturing_date' => now()->subMonths(1)->format('Y-m-d'),
                'expiry_date' => now()->addMonths(24)->format('Y-m-d'),
                'quantity' => 50,
                'unit_cost' => 300, // 300 santim
                'flag' => 'ok',
            ],
        ],
    ], $pm);

    $confirm->execute($receipt, $pm);

    $query = new StockOnHandQuery;
    $products = $query->builder($pm)->get();

    expect($products)->toHaveCount(1);
    $found = $products->first();
    expect($found->batches)->toHaveCount(2);

    $metrics = StockOnHandQuery::metrics($found, true);
    expect($metrics['total_sellable'])->toBe(150)
        ->and($metrics['total_quarantine'])->toBe(0)
        ->and($metrics['total_valuation'])->toBe((100 * 250) + (50 * 300));
});

test('quarantine quantity shown separately', function () {
    $product = makeStockProduct();
    $staff = ps();
    $pm = pm();

    $submit = new SubmitStockReceipt;
    $confirm = new ConfirmStockReceipt;

    $receipt = $submit->execute([
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'STOCK_TEST_02',
        'lines' => [
            [
                'product_id' => $product->id,
                'batch_no' => 'BATCH-DAMAGED-01',
                'manufacturing_date' => now()->subMonths(2)->format('Y-m-d'),
                'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
                'quantity' => 25,
                'unit_cost' => 100,
                'flag' => 'damaged',
            ],
            [
                'product_id' => $product->id,
                'batch_no' => 'BATCH-OK-01',
                'manufacturing_date' => now()->subMonths(2)->format('Y-m-d'),
                'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
                'quantity' => 75,
                'unit_cost' => 100,
                'flag' => 'ok',
            ],
        ],
    ], $staff);

    $confirm->execute($receipt, $pm);

    $metrics = StockOnHandQuery::metrics($product->fresh(['batches.stocks']), false);
    expect($metrics['total_sellable'])->toBe(75)
        ->and($metrics['total_quarantine'])->toBe(25);

    actAs($staff);
    Livewire::test(StockIndex::class)
        ->assertSee('75')
        ->assertSee('25');
});

test('ledger balances reconcile with stock', function () {
    $product = makeStockProduct();
    $staff = ps();
    $pm = pm();

    $submit = new SubmitStockReceipt;
    $confirm = new ConfirmStockReceipt;

    $receipt = $submit->execute([
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'STOCK_TEST_03',
        'lines' => [
            [
                'product_id' => $product->id,
                'batch_no' => 'RECON-01',
                'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
                'quantity' => 80,
                'flag' => 'ok',
            ],
        ],
    ], $staff);

    $confirm->execute($receipt, $pm);

    $batch = Batch::where('batch_no', 'RECON-01')->first();
    $currentStock = BatchStock::where('batch_id', $batch->id)->value('quantity');

    $latestMovement = StockMovement::where('batch_id', $batch->id)->latest('id')->first();
    expect($latestMovement->balance_after)->toBe($currentStock)
        ->and($latestMovement->quantity)->toBe(80);

    actAs($pm);
    Livewire::test(StockLedger::class, ['batch_id' => $batch->id])
        ->assertSee('RECON-01')
        ->assertSee('+80')
        ->assertSee('80');
});

test('staff do not receive cost in data or HTML (AC-16)', function () {
    $product = makeStockProduct();
    $pm = pm();
    $staff = ps();

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'COST-TEST-01',
        'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
        'unit_cost' => 4500, // 45.00 ETB
    ]);

    StockMovementService::post($batch, 10, 'receipt', $product, $pm, 'sellable');

    // 1. In Query Object data:
    $staffQuery = new StockOnHandQuery;
    $staffResult = $staffQuery->builder($staff)->first();
    $batchFromStaffQuery = $staffResult->batches->first();

    // Verify unit_cost is NOT set or selected on the batch model for staff
    expect($batchFromStaffQuery->getAttributes())->not->toHaveKey('unit_cost');

    // 2. In Livewire HTML:
    actAs($staff);
    Livewire::test(StockIndex::class)
        ->assertDontSee('45.00 ETB')
        ->assertDontSee('Valuation')
        ->assertDontSee('Unit Cost');

    // 3. But PM DOES see cost:
    actAs($pm);
    Livewire::test(StockIndex::class)
        ->assertSee('Valuation')
        ->assertSee('450.00 ETB'); // 10 * 45.00 ETB
});

test('export denied to staff (AC-16)', function () {
    $staff = ps();
    $pm = pm();

    actAs($staff);
    $this->get('/inventory/stock/export/csv')->assertForbidden();
    $this->get('/inventory/stock/export/pdf')->assertForbidden();
    $this->get('/inventory/ledger/export/csv')->assertForbidden();
    $this->get('/inventory/ledger/export/pdf')->assertForbidden();

    actAs($pm);
    $this->get('/inventory/stock/export/csv')->assertOk();
    $this->get('/inventory/stock/export/pdf')->assertOk();
    $this->get('/inventory/ledger/export/csv')->assertOk();
    $this->get('/inventory/ledger/export/pdf')->assertOk();
});

test('attempts to edit the ledger fail (E1)', function () {
    $pm = pm();
    $product = makeStockProduct();
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'IMMUTABLE-01',
        'expiry_date' => now()->addYear(),
    ]);

    $movement = StockMovementService::post($batch, 50, 'receipt', $product, $pm);

    expect(fn () => $movement->update(['quantity' => 100]))->toThrow(RuntimeException::class);
    expect(fn () => $movement->delete())->toThrow(RuntimeException::class);

    // Verify no update or delete routes exist for ledger
    foreach (app('router')->getRoutes() as $route) {
        if (str_contains($route->uri(), 'inventory/ledger')) {
            expect($route->methods())->not->toContain('PUT')
                ->not->toContain('PATCH')
                ->not->toContain('DELETE');
        }
    }
});

test('500,000 ledger rows still page under 2 s (seed and measure)', function () {
    $user = pm();
    $product = makeStockProduct();
    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'PERF-BATCH',
        'expiry_date' => now()->addYear(),
    ]);

    // Use fast SQLite CTE bulk generator to insert 500,000 movements
    DB::statement("
        WITH RECURSIVE cnt(x) AS (
            SELECT 1
            UNION ALL
            SELECT x + 1 FROM cnt WHERE x < 500000
        )
        INSERT INTO stock_movements (batch_id, type, quantity, balance_after, stock_status, source_type, source_id, user_id, created_at)
        SELECT {$batch->id}, 'receipt', 1, x, 'sellable', 'App\\\\Models\\\\StockReceipt', 1, {$user->id}, '2026-01-01 10:00:00'
        FROM cnt
    ");

    $count = DB::table('stock_movements')->count();
    expect($count)->toBeGreaterThanOrEqual(500000);

    // Measure page execution time
    $query = new LedgerQuery(['batch_id' => $batch->id]);
    $start = microtime(true);
    $page = $query->builder($user)->paginate(25);
    $elapsed = microtime(true) - $start;

    expect($page->items())->toHaveCount(25)
        ->and($elapsed)->toBeLessThan(2.0);
});

test('system administrator is denied all inventory and ledger screens', function () {
    $sa = sa();
    actAs($sa);

    $this->get('/inventory/stock')->assertForbidden();
    $this->get('/inventory/ledger')->assertForbidden();
    $this->get('/inventory/stock/export/csv')->assertForbidden();
    $this->get('/inventory/ledger/export/csv')->assertForbidden();
});
