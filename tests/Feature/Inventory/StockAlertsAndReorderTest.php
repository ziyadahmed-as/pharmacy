<?php

use App\Actions\Inventory\SubmitStockReceipt;
use App\Livewire\Alerts\Index as AlertsIndex;
use App\Models\Batch;
use App\Models\Product;
use App\Models\StockAlert;
use App\Services\ReorderListQuery;
use App\Services\StockMovementService;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

function makeAlertTestProduct(array $attr = []): Product
{
    return Product::create(array_merge([
        'brand' => 'Ciprofloxacin 500mg',
        'generic_name' => 'Ciprofloxacin',
        'strength' => '500mg',
        'dosage_form' => 'Tablet',
        'classification' => 'rx',
        'reorder_level' => 50,
        'min_level' => 20,
        'max_level' => 100,
        'preferred_supplier_name' => 'Ethiopian Pharmaceuticals Supply Service',
        'is_active' => true,
    ], $attr));
}

test('alerts appear at the right thresholds and only once', function () {
    $product = makeAlertTestProduct();
    $pm = pm();

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'ALERT-B1',
        'expiry_date' => now()->addYear()->format('Y-m-d'),
    ]);

    // Stock is 40: at or below reorder level (50) and above min level (20)
    StockMovementService::post($batch, 40, 'receipt', $product, $pm, 'sellable');

    Artisan::call('stock:evaluate-alerts');

    $alert = StockAlert::where('product_id', $product->id)->first();
    expect($alert)->not->toBeNull()
        ->and($alert->type)->toBe('low_stock')
        ->and($alert->severity)->toBe('warning')
        ->and($alert->current_quantity)->toBe(40);

    // Run again: must not duplicate
    Artisan::call('stock:evaluate-alerts');
    expect(StockAlert::where('product_id', $product->id)->count())->toBe(1);
});

test('critical flag below minimum (A1)', function () {
    $product = makeAlertTestProduct();
    $pm = pm();

    $batch = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'CRIT-B1',
        'expiry_date' => now()->addYear()->format('Y-m-d'),
    ]);

    // Stock is 15: strictly below min level (20)
    StockMovementService::post($batch, 15, 'receipt', $product, $pm, 'sellable');

    Artisan::call('stock:evaluate-alerts');

    $alert = StockAlert::where('product_id', $product->id)->first();
    expect($alert)->not->toBeNull()
        ->and($alert->type)->toBe('critical_stock')
        ->and($alert->severity)->toBe('critical')
        ->and($alert->current_quantity)->toBe(15);
});

test('reorder list math (suggested quantity: max minus on hand)', function () {
    $pm = pm();

    // Product 1: with max_level 100, on hand 15 -> suggested 85
    $p1 = makeAlertTestProduct(['brand' => 'P1 Test', 'reorder_level' => 50, 'min_level' => 20, 'max_level' => 100]);
    $b1 = Batch::create(['product_id' => $p1->id, 'batch_no' => 'P1-B', 'expiry_date' => now()->addYear()]);
    StockMovementService::post($b1, 15, 'receipt', $p1, $pm, 'sellable');

    // Product 2: with max_level 0 (not set), reorder 40, on hand 30 -> suggested (40*2) - 30 = 50
    $p2 = makeAlertTestProduct(['brand' => 'P2 Test', 'reorder_level' => 40, 'min_level' => 10, 'max_level' => 0]);
    $b2 = Batch::create(['product_id' => $p2->id, 'batch_no' => 'P2-B', 'expiry_date' => now()->addYear()]);
    StockMovementService::post($b2, 30, 'receipt', $p2, $pm, 'sellable');

    // Product 3: healthy stock (above reorder) -> should not be on reorder list
    $p3 = makeAlertTestProduct(['brand' => 'P3 Healthy', 'reorder_level' => 30, 'max_level' => 100]);
    $b3 = Batch::create(['product_id' => $p3->id, 'batch_no' => 'P3-B', 'expiry_date' => now()->addYear()]);
    StockMovementService::post($b3, 50, 'receipt', $p3, $pm, 'sellable');

    $reorderList = ReorderListQuery::get();

    expect($reorderList)->toHaveCount(2);

    $item1 = $reorderList->firstWhere('product.id', $p1->id);
    expect($item1['on_hand'])->toBe(15)
        ->and($item1['suggested_qty'])->toBe(85)
        ->and($item1['is_critical'])->toBeTrue()
        ->and($item1['preferred_supplier'])->toBe('Ethiopian Pharmaceuticals Supply Service');

    $item2 = $reorderList->firstWhere('product.id', $p2->id);
    expect($item2['on_hand'])->toBe(30)
        ->and($item2['suggested_qty'])->toBe(50)
        ->and($item2['is_critical'])->toBeFalse();
});

test('near expiry and expired batch flagged (AC-33)', function () {
    $product = makeAlertTestProduct();
    $pm = pm();

    // Batch Expired: expired yesterday
    $bExpired = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'B-EXPIRED',
        'expiry_date' => now()->subDay()->format('Y-m-d'),
    ]);
    StockMovementService::post($bExpired, 10, 'receipt', $product, $pm, 'sellable');

    // Batch 30d: expires in 20 days (window 30)
    $b30 = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'B-30D',
        'expiry_date' => now()->addDays(20)->format('Y-m-d'),
    ]);
    StockMovementService::post($b30, 20, 'receipt', $product, $pm, 'sellable');

    // Batch 60d: expires in 50 days (window 60)
    $b60 = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'B-60D',
        'expiry_date' => now()->addDays(50)->format('Y-m-d'),
    ]);
    StockMovementService::post($b60, 30, 'receipt', $product, $pm, 'sellable');

    Artisan::call('stock:evaluate-alerts');

    $expiredAlert = StockAlert::where('batch_id', $bExpired->id)->first();
    expect($expiredAlert)->not->toBeNull()
        ->and($expiredAlert->type)->toBe('expired')
        ->and($expiredAlert->severity)->toBe('critical');

    $alert30 = StockAlert::where('batch_id', $b30->id)->first();
    expect($alert30)->not->toBeNull()
        ->and($alert30->type)->toBe('near_expiry')
        ->and($alert30->threshold_days)->toBe(30);

    $alert60 = StockAlert::where('batch_id', $b60->id)->first();
    expect($alert60)->not->toBeNull()
        ->and($alert60->type)->toBe('near_expiry')
        ->and($alert60->threshold_days)->toBe(60);

    // Duplicate check
    Artisan::call('stock:evaluate-alerts');
    expect(StockAlert::where('batch_id', $bExpired->id)->count())->toBe(1);
});

test('receipt warning when receipt would exceed maximum level', function () {
    $product = makeAlertTestProduct(['max_level' => 100]);
    $pm = pm();

    $b = Batch::create([
        'product_id' => $product->id,
        'batch_no' => 'MAX-TEST',
        'expiry_date' => now()->addYear(),
    ]);
    StockMovementService::post($b, 80, 'receipt', $product, $pm, 'sellable');

    $submit = new SubmitStockReceipt;
    $receipt = $submit->execute([
        'supplier_name' => 'EPSS',
        'receipt_date' => now()->format('Y-m-d'),
        'idempotency_key' => 'MAX_WARN_RECEIPT_01',
        'lines' => [
            [
                'product_id' => $product->id,
                'batch_no' => 'MAX-TEST-2',
                'expiry_date' => now()->addMonths(12)->format('Y-m-d'),
                'quantity' => 35, // 80 + 35 = 115 > 100
                'flag' => 'ok',
            ],
        ],
    ], $pm);

    expect($receipt->notes)->toContain('exceeds maximum level of 100');
});

test('export works for PM and is denied to staff', function () {
    $pm = pm();
    $staff = ps();

    actAs($staff);
    $this->get('/inventory/reorder/export/csv')->assertForbidden();
    $this->get('/inventory/reorder/export/pdf')->assertForbidden();

    actAs($pm);
    $this->get('/inventory/reorder/export/csv')->assertOk();
    $this->get('/inventory/reorder/export/pdf')->assertOk();
});

test('PM sees the screen and marks seen; staff see alerts read-only', function () {
    $product = makeAlertTestProduct();
    $pm = pm();
    $staff = ps();

    $alert = StockAlert::create([
        'product_id' => $product->id,
        'type' => 'low_stock',
        'severity' => 'warning',
        'current_quantity' => 10,
        'reorder_level' => 50,
        'message' => 'Ciprofloxacin stock is low',
        'status' => 'active',
        'dedupe_key' => 'test-read-only-alert',
    ]);

    // Staff sees alerts read-only:
    actAs($staff);
    Livewire::test(AlertsIndex::class)
        ->assertSee('Ciprofloxacin')
        ->assertDontSee('✓ Mark seen')
        ->call('markSeen', $alert->id)
        ->assertForbidden();

    // PM sees screen and can mark seen:
    actAs($pm);
    Livewire::test(AlertsIndex::class)
        ->assertSee('Ciprofloxacin')
        ->assertSee('Mark seen')
        ->call('markSeen', $alert->id);

    expect($alert->fresh()->status)->toBe('seen')
        ->and($alert->fresh()->seen_by)->toBe($pm->id);
});

test('system administrator is denied all alerts screens', function () {
    $sa = sa();
    actAs($sa);

    $this->get('/inventory/alerts')->assertForbidden();
    $this->get('/inventory/reorder/export/csv')->assertForbidden();
});
