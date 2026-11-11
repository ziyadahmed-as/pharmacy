<?php

use App\Actions\Sales\CompleteSale;
use App\Actions\Shifts\OpenShift;
use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Sale;
use App\Models\Till;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * NFR-1 Performance Verification: 20,000 products benchmark.
 * BRD: BO-4, NFR-1.
 * Tests search response time and sale completion throughput.
 */
beforeEach(fn () => seedRoles());

it('measures NFR-1 performance targets with 20,000 products', function () {
    $staff = ps();
    $till = Till::create(['name' => 'Bench Till', 'is_active' => true]);
    app(OpenShift::class)->execute($staff, $till, 50000);

    // 1. Bulk insert 20,000 products in chunks of 2,000
    $chunkSize = 2000;
    $totalProducts = 20000;
    $now = now()->toDateTimeString();

    for ($i = 0; $i < $totalProducts; $i += $chunkSize) {
        $productsChunk = [];
        for ($j = 1; $j <= $chunkSize; $j++) {
            $num = $i + $j;
            $productsChunk[] = [
                'brand' => "Product Brand {$num}",
                'generic_name' => "Generic Substance {$num}",
                'strength' => '500mg',
                'dosage_form' => 'tablet',
                'pack_size' => '10x10',
                'unit' => 'piece',
                'classification' => 'otc',
                'barcode' => sprintf('BC%08d', $num),
                'is_active' => true,
                'loose_allowed' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('products')->insert($productsChunk);
    }

    expect(Product::count())->toBeGreaterThanOrEqual(20000);

    // Target product for lookup & checkout
    $targetProduct = Product::where('barcode', 'BC00010000')->first();
    expect($targetProduct)->not->toBeNull();

    // 2. Measure Barcode Search Time
    $startBarcode = microtime(true);
    $foundByBarcode = Product::where('is_active', true)
        ->where('barcode', 'BC00010000')
        ->first();
    $barcodeDurationMs = (microtime(true) - $startBarcode) * 1000;

    expect($foundByBarcode?->id)->toBe($targetProduct->id)
        ->and($barcodeDurationMs)->toBeLessThan(100.0); // Target < 100ms (typically < 5ms with index)

    // 3. Measure Name Prefix / Text Search Time
    $startSearch = microtime(true);
    $searchResults = Product::where('is_active', true)
        ->where('brand', 'like', 'Product Brand 1000%')
        ->limit(10)
        ->get();
    $searchDurationMs = (microtime(true) - $startSearch) * 1000;

    expect($searchResults)->not->toBeEmpty()
        ->and($searchDurationMs)->toBeLessThan(250.0); // Target < 250ms

    // 4. Setup stock and price for 5 products to test sale completion
    $saleItems = [];
    $totalAmountSantim = 0;

    for ($k = 1; $k <= 5; $k++) {
        $p = Product::where('barcode', sprintf('BC%08d', $k * 1000))->first();

        // Create price
        ProductPrice::create([
            'product_id' => $p->id,
            'price' => 2500,
            'effective_from' => now()->subDay(),
            'reason' => 'Bench price',
            'set_by' => $staff->id,
        ]);

        // Create batch with stock
        $batch = Batch::create([
            'product_id' => $p->id,
            'batch_no' => "BENCH-B{$k}",
            'expiry_date' => now()->addYear()->format('Y-m-d'),
        ]);

        BatchStock::create([
            'batch_id' => $batch->id,
            'quantity' => 100,
            'status' => 'sellable',
        ]);

        $saleItems[] = [
            'product_id' => $p->id,
            'quantity' => 2,
        ];
        $totalAmountSantim += 2 * 2500;
    }

    // 5. Measure CompleteSale execution time under 20k catalog
    $startSale = microtime(true);
    $sale = app(CompleteSale::class)->execute(
        user: $staff,
        items: $saleItems,
        payments: [['method' => 'cash', 'amount' => $totalAmountSantim]],
        idempotencyKey: (string) Str::uuid()
    );
    $saleDurationMs = (microtime(true) - $startSale) * 1000;

    expect($sale)->toBeInstanceOf(Sale::class)
        ->and($sale->total)->toBe($totalAmountSantim)
        ->and($saleDurationMs)->toBeLessThan(1000.0); // Target < 1000ms

    // Record measurements to console / report
    $report = sprintf(
        "NFR-1 Benchmark Results (20,000 products):\n".
        "- Barcode scan lookup: %.2f ms (target < 100 ms)\n".
        "- Brand/name search query: %.2f ms (target < 250 ms)\n".
        "- 5-item transactional sale completion: %.2f ms (target < 1000 ms)\n",
        $barcodeDurationMs,
        $searchDurationMs,
        $saleDurationMs
    );

    // Save benchmark report
    file_put_contents(base_path('docs/decisions/0016-nfr-1-performance-measurements.md'), "# 0016. NFR-1 Performance Measurements\n\n```\n{$report}```\n");
});
